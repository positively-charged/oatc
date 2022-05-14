<?php

declare( strict_types = 1 );

namespace Checking;

use Ctce\Evaluator;
use Expr;
use Lexing\Position;
use Node;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description;
use Typing\Description as Desc;
use Typing\InstanceChecker;
use Typing\InstanceCheckerUsage;
use Typing\Presenter;
use Typing\PresenterUsage;
use Typing\SamenessChecker;
use Typing\Type;

class ExprChecker {
   use DescriberUsage;
   use PresenterUsage;
   use InstanceCheckerUsage;

   private \User $user;
   private \Typing\TypeChecker $typeChecker;
   private SimpleExprChecker $simpleExprChecker;
   private \Ctce\Evaluator $evaluator;
   private \Scope $scope;
   private int $loopDepth;
   private bool $returning;
   private bool $insideVirtExpr;
   public bool $typeContext;
   private ?\Func $func;

   public function __construct( \User $user, \Typing\TypeChecker $typeChecker,
      SimpleExprChecker $simpleExprChecker, \Scope $scope,
      private Describer $typeDescriber,
      private Presenter $typePresenter,
      private InstanceChecker $instanceChecker,
      private SamenessChecker $samenessChecker,
      Evaluator $evaluator,
      private ModuleChecker $moduleChecker,
      private \Task $task ) {
      $this->user = $user;
      $this->typeChecker = $typeChecker;
      $this->simpleExprChecker = $simpleExprChecker;
      $this->scope = $scope;
      $this->loopDepth = 0;
      $this->returning = false;
      $this->insideVirtExpr = false;
      $this->func = null;
      $this->evaluator = $evaluator;
      $this->typeContext = false;
   }

   public function checkConstantExpr( Expr $expr ): Value {
      $result = $this->checkExpr( $expr );
      if ( ! $result->constant ) {
         $this->user->diag( DIAG_ERR, $expr->pos,
            "expression not constant" );
         $this->user->bail();
      }
      return $result;
   }

   public function checkTypeContextExpr( Expr $expr ): Value {
      $this->typeContext = true;
      $result = $this->checkConstantExpr( $expr );
      if ( $this->describeValue( $result ) === Desc::STRUCT_TYPE ) {
         $result->type->structure = $result->inhabitant;
         $result->type->spec = TYPESPEC_STRUCT;
         $result->inhabitant = null;
      }
      $this->typeContext = false;
      return $result;
   }

   public function checkExpr( Expr $expr ): Value {
      $this->insideVirtExpr = $expr->virtual;

      $result = $this->visitExprRoot( $expr->root );

      if ( $expr->virtual ) {
         if ( ! $result->constant ) {
            $this->user->diag( DIAG_ERR, $expr->pos,
               "expression not constant" );
            $this->user->bail();
         }

         $this->evaluator->evalVirtExpr( $expr );
         $result = new Value();
         $result->type = $expr->type;
         $result->inhabitant = $expr->value;

         return $result;
      }
      else {
         $expr->type = $result->type;
         $expr->constant = $result->constant;
         $expr->value = $result->inhabitant;
      }

      return $result;
   }

   private function visitExprRoot( Node $node ): Value {
      $result = new StmtResult();
      if ( $node instanceof \BlockStmt ) {
         return $this->checkBlockStmt( $node );
      }
      else if ( $node instanceof \IfStmt ) {
         return $this->checkIfStmt( $node );
      }
      else if ( $node instanceof \MatchExpr ) {
         return $this->checkMatchExpr( $node );
      }
      else if ( $node instanceof \WhileStmt ) {
         return $this->checkWhileStmt( $node, $result );
      }
      else if ( $node instanceof \ForLoop ) {
         return $this->checkForLoop( $node, $result );
      }
      else if ( $node instanceof \Jump ) {
         return $this->checkJump( $node, $result );
      }
      else if ( $node instanceof \ReturnStmt ) {
         return $this->checkReturnStmt( $node, $result );
      }
      else if ( $node instanceof \DropExpr ) {
         return $this->checkDrop( $node );
      }
      else {
         return $this->simpleExprChecker->checkSimpleExpr( $node );
      }
   }

   public function checkBlockStmt( \BlockStmt $stmt,
      bool $topBlockStmt = false ): Value {
      if ( ! $topBlockStmt ) {
         $this->scope->enter();
      }
      $result = $this->checkStmtList( $stmt );

      // All references must be collected.
      /*
      $unmoved = $this->scope->getUnmoved();
      if ( ! empty( $unmoved ) ) {
         foreach ( $unmoved as $binding ) {
            $this->user->diag( DIAG_ERR, $binding->pos,
               "unmoved binding" );
         }
         $this->user->bail();
      } */

      //$result = new Result();
      //$result->evaluable = $result->evaluable;
      //$result->constant = $result->constant;
      if ( count( $stmt->stmts ) > 0 &&
         end( $stmt->stmts ) instanceof \ExprStmt) {
         $exprStmt = end( $stmt->stmts );
         if ( $exprStmt->yield ) {
            //$result->type = $exprStmt->expr->type;
            $stmt->returnValueExprStmt = $exprStmt;
            //$result = $exprStmt->result;

            // Move value.
            if ( $exprStmt->result !== null &&
               $exprStmt->result->binding !== null ) {
               $exprStmt->result->binding->value = new Value();
            }
         }
      }

      if ( ! $topBlockStmt ) {
         $this->scope->leave();
      }

      return $result;
   }

   public function checkFuncBody( \Func $func ): void {
      foreach ( $func->params as $param ) {
         $result = new Value();
         $result->type->spec = $param->type->spec;
         $result->type->structure = $param->type->structure;
         $result->type->enumeration = $param->type->enumeration;
         $result->type->trait = $param->type->trait;
         $result->type->borrowed = $param->type->borrowed;
         $result->type->mutable = $param->type->mutable;
         $result->type->ptr = $param->type->ptr;
         $result->type->args = $param->type->args;
         $result->type->value = $param->value->inhabitant;
         //$result->type = $result->type->createInstance();
         $result->mutableBinding = $param->rebindable;

         $binding = $this->scope->get( $param->name );
         if ( $binding !== null ) {
            $result->binding = $binding;
            $binding->value = $result;
         }
         else {
            if ( $param === $func->params[ 0 ] ) {
               $this->scope->bind( 'self', $param );
               $param->name = 'self';
            }
         }
      }

      $this->func = $func;
      $this->returning = false;
      $result = $this->checkBlockStmt( $func->body, true );
      if ( $this->describe( $result->type ) !== Desc::NEVER &&
         $this->describe( $result->type ) !== Desc::ERR ) {
         if ( ! $this->isVoid( $result->type ) &&
            $func->body->returnValueExprStmt !== null ) {
            $this->checkReturnValue( $func->body->returnValueExprStmt->expr->pos,
               $result );
         }
         else {
            if ( ! $this->isVoid( $func->returnType ) && ! $this->returning ) {
               $this->user->diag( DIAG_ERR, $func->pos,
                  "function `%s` is expected to return a value, but no value " .
                  "is returned", $func->name );
               $this->user->bail();
            }
         }
      }

      foreach ( $func->params as $param ) {
         if ( $param->type->borrowed ) {
            $binding = $this->scope->get( $param->name );
            $refinedType = $param->type;
            /*
            if ( $func->returnTypeExpr !== null ) {
               foreach ( $func->returnTypeExpr->options as $option ) {
                  if ( $this->isInstanceOf( $result->type,
                     $option->type ) ) {
                     $refinement = $option->findRefinement( $param->name );
                     if ( $refinement !== null ) {
                        $refinedType = $refinement->refinedType;
                     }
                  }
               }
            }
            if ( ! $this->typeChecker->isInstanceOf( $refinedType,
               $binding->value->type ) ) {
               $this->user->diag( DIAG_ERR, $param->pos,
                  'refinements made to `%s` (`%s`) need to be reported',
                  $param->name, $this->typeChecker->presentType(
                     $binding->value->type ) );
               $this->user->bail();
            }*/
         }
      }

      $options = $this->getReturnOptions( $func->returnType );
      foreach ( $options as $option ) {
         if ( $this->isInstanceOf( $result, $option ) ) {
            foreach ( $option->refinements as $name => $refinement ) {
               $object = $this->scope->get( $name );
               if ( $object === null ) {
                  $this->user->diag( DIAG_ERR, $refinement->pos,
                     "cannot refine unknown object `%s`", $name );
                  $this->user->bail();
               }

               if ( $object->value === null ||
                  ! $this->isInstanceOf( $object->value,
                     $refinement->refinedType ) ) {
                  if ( $object->value !== null ) {
                     $this->user->diag( DIAG_ERR, $refinement->pos,
                        "refinement unsatisified (`%s` is of type `%s`)",
                        $refinement->target,
                        $this->presentType( $object->value->type ) );
                  }
                  else {
                     $this->user->diag( DIAG_ERR, $refinement->pos,
                        "refinement unsatisified (`%s` remains unrefined)",
                        $refinement->target );
                  }
                  $this->user->bail();
               }
            }
         }
      }

      $this->func = null;
   }

   private function err( Position $pos, string $message, ... $args ): never {
      $result = new Value();
      $result->type->spec = TYPESPEC_ERR;
      $result->diag = $this->user->diag( DIAG_ERR, $pos, $message,
         ... $args );
      throw new CheckErr( $result );
   }

   private function checkStmtList( \BlockStmt $stmt ): Value {
      $stmtResult = new Value();
      $neverResult = null;
      $unevaluable = false;
      $unconstant = false;
      for ( $i = 0; $i < count( $stmt->stmts ); ++$i ) {
         if ( $neverResult !== null ) {
            if ( ! $stmt->stmts[ $i ]->expr->virtual ) {
               $result = new Value();
               $result->type->spec = TYPESPEC_ERR;
               $result->diag = $this->user->diag( DIAG_ERR,
                  $stmt->stmts[ $i ]->expr->pos, 'unreachable code' );
               break;
            }
         }

         $result = $this->checkStmt( $stmt->stmts[ $i ] );

         if ( $this->describe( $result->type ) === Desc::NEVER ||
            $this->describe( $result->type ) === Desc::ERR ) {
            $neverResult = $result;
         }
         /*
         if ( $result->err != null ) {
            $errStmt = new \ErrStmt();
            $errStmt->err = $result->err;
            $errStmt->faultyStmt = $stmt->stmts[ $i ];
            $stmt->stmts[ $i ] = $errStmt;
            break;
         } */

         if ( ! $result->evaluable ) {
            $unevaluable = true;
         }
         if ( ! $result->constant ) {
            $unconstant = true;
         }

         // Only the last expression statement can indicate the result of a
         // block expression.
         if ( $stmt->stmts[ $i ] instanceof \ExprStmt &&
            $stmt->stmts[ $i ]->yield &&
            ! $stmt->stmts[ $i ]->expr->compound &&
            $i + 1 != count( $stmt->stmts ) ) {
            $this->user->diag( DIAG_ERR, $stmt->stmts[ $i ]->expr->pos,
               "statement must be terminated with a `;`" );
            $this->user->bail();
         }

         if ( $stmt->stmts[ $i ] instanceof \ExprStmt &&
            $stmt->stmts[ $i ]->yield && $i + 1 === count( $stmt->stmts ) ) {
            $stmtResult = $result;
         }
      }

      if ( ! $unevaluable ) {
         $stmt->evaluable = true;
      }
      if ( ! $unconstant ) {
         $stmt->constant = true;
      }

      if ( $neverResult !== null ) {
         $stmtResult = $neverResult;
      }

      return $stmtResult;
   }

   private function checkStmt( \Node $stmt ): Value {
      if ( $stmt instanceof \ExprStmt ) {
         $result = $this->checkExprStmt( $stmt );
         return $result;
      }
      else {
         throw new \Exception();
      }
   }

   private function checkExprStmt( \ExprStmt $stmt ): Value {
      try {
         $result = $this->checkExpr( $stmt->expr );
         $stmt->result = $result;

         $this->simpleExprChecker->matchAgainstArms( $result, $stmt->arms );
      }
      catch ( CheckErr $err ) {
         $result = $err->result;
         $stmt->result = $result;
      }

      return $result;

      /*
      if ( ! empty( $stmt->binding ) ) {
         if ( ! empty( $stmt->bindingType ) ) {
            $this->checkType( $stmt->bindingType );
            if ( ! $value->type->isInstanceOf( $stmt->bindingType ) ) {
               printf( "error: incorrect binding type\n" );
               throw new Exception();
            }
         }
         else {
            $stmt->bindingType = $value->type;
         }
         $var = new Variable();
         $var->name = $stmt->binding;
         $var->type = $stmt->bindingType;

         $binding = new Binding();
         $binding->value = $value;
         $this->scope->setBinding( $var->name, $binding );
      }*/
   }

   private function checkIfStmt( \IfStmt $stmt ): Value {
      $this->scope->enter();

      $union = new \Typing\AnonymousUnion( $this->samenessChecker,
         $this->typeDescriber );

      $ifResult = null;
      foreach ( $stmt->ifs as $item ) {
         $result = $this->checkIfItem( $item );
         $union->addMember( $result->type );
         if ( $ifResult !== null ) {
         /*
            if ( ! $this->typeChecker->isInstanceOf( $result->type,
               $ifResult->type ) ) {
               $this->user->diag( DIAG_ERR, $item->pos,
                  "`elif` returns a different type than previous if items" );
               $this->user->bail();
            }*/
         }
         else {
            $ifResult = $result;
         }
      }

      $unevaluable = false;
      if ( $stmt->elseBody != null ) {
         $elseResult = $this->checkBlockStmt( $stmt->elseBody, false );
         $union->addMember( $elseResult->type );
         /*
         if ( ! $this->typeChecker->isInstanceOf( $elseResult->type,
            $ifResult->type ) ) {
            $this->user->diag( DIAG_ERR, $item->pos,
               "`else` clause of `if` expression has a different type" );
            $this->user->bail();
         }
         */
         if ( ! $elseResult->evaluable ) {
            $unevaluable = true;
         }
      }
      else {
         if (
            ! $this->isVoid( $ifResult->type ) &&
            $this->describe( $ifResult->type ) !== Desc::NEVER &&
            $this->describe( $ifResult->type ) !== Desc::ERR ) {
            $this->user->diag( DIAG_ERR, $stmt->ifs[ 0 ]->pos,
               "when an `if` expression returns a value, an `else` clause " .
               "must be specified" );
            $this->user->bail();
         }
      }

      if ( ! $unevaluable ) {
         $ifResult->evaluable = true;
      }

      $this->scope->leave();

      $stmt->type = $union->createType();
      $ifResult->type = $stmt->type;
      //$stmt->type = $ifResult->type;

      return $ifResult;
   }

   private function checkIfItem( \IfItem $item ): Value {
      $this->scope->enter( \SCOPE_CONDITION );

      $result = new Value();
      $cond = $this->checkExpr( $item->cond );
      if ( $this->describe( $cond->type ) !== Desc::BOOL ) {
         $this->err( $item->cond->pos,
            "condition must be of `bool` type" );
      }

      // To help prevent the rare case of assignment to a bool variable when
      // the user meant equality because they forgot an extra `=`, disallow a
      // bare assignment in a condition; that is, if an assignment is to be
      // used, the user should compare the assignment against another value.
      if ( $cond->assigned ) {
         $this->err( $item->cond->pos,
            'bare assignment not allowed in condition' );
      }

      foreach ( $cond->type->refinements as $refinement ) {
         if ( $this->describe( $refinement->result->type ) === Desc::BOOL &&
            $refinement->result->inhabitant === 1 ) {

            $refinement->target->type = $refinement->type;

/*
            $binding = $this->scope->get( $refinement->target );
            if ( $binding !== null ) {
               $value = clone $binding->value;
               $value->type = $refinement->type;
               $b = $this->scope->getInCurrentScope( $refinement->target );
               $value->binding = $b;
               $b->value = $value;
               $b->node = $binding->node;


               //var_dump( $b->value );
            }
*/
         }
      }

      $unevaluable = false;
      if ( ! $cond->evaluable ) {
         $unevaluable = true;
      }

      $body = $this->checkBlockStmt( $item->body, false );
      $this->scope->leave();


      if ( ! $body->evaluable ) {
         $unevaluable = true;
      }
      if ( ! $this->isVoid( $body->type ) ) {
         $result->type = $body->type;
         //$result->message = $body->message;
      }

      return $result;
   }

   private function checkMatchExpr( \MatchExpr $matchExpr ): Value {
      $cond = $this->checkExpr( $matchExpr->cond );
      return $this->simpleExprChecker->matchAgainstArms( $cond, $matchExpr->arms );
   }

   private function checkSwitchStmt( \SwitchStmt $stmt ): void {
      //$check = new SwitchStmtCheck( $stmt );
      //$check->run();
      $cond = $this->checkExpr( $stmt->cond );
      $this->checkSwitchCases( $stmt, $cond );
   }

   private function checkSwitchCases( \SwitchStmt $stmt, Value $cond ): void {
      $values = [];
      $switchHasDefault = false;

      foreach ( $stmt->cases as $case ) {
         //$this->checkCase( $case );
         foreach ( $case->values as $expr ) {
            $value = $this->checkExpr( $expr );
            if ( ! $this->isInstanceOf( $value, $cond->type ) ) {
               $this->user->diag( DIAG_ERR, $expr->pos,
                  "case type (`%s`) different from switch condition type (`%s`)",
                  $this->presentType( $value->type ),
                  $this->presentType( $cond->type ) );
               $this->user->bail();
            }
            if ( isset( $values[ $expr->value ] ) ) {
               printf( "error: duplicate case `%d`\n", $expr->value );
               throw new \Exception();
            }
            $values[ $expr->value ] = true;
         }
         if ( $case->isDefault ) {
            if ( $switchHasDefault ) {
               printf( "error: duplicate default case\n" );
               throw new \Exception();
            }
            $switchHasDefault = true;
         }
         $this->checkBlockStmt( $case->body );
      }
   }

   private function checkWhileStmt( \WhileStmt $stmt,
      StmtResult $stmtResult ): Value {
      ++$this->loopDepth;
      $this->scope->enter();
      $this->scope->enter( \SCOPE_CONDITION );
      $cond = $this->checkExpr( $stmt->cond );
      $this->scope->leave();
      if ( $this->describe(  $stmt->cond->type ) !== Desc::BOOL ) {
         $this->err( $stmt->cond->pos,
            'condition must be of Bool type' );
      }
      $unevaluable = false;
      if ( ! $cond->evaluable ) {
         $unevaluable = true;
      }

      $body = $this->checkBlockStmt( $stmt->body, false );
      if ( ! $body->evaluable ) {
         $unevaluable = true;
      }

      if ( $stmt->endfully != null ) {
         $result = $this->checkBlockStmt( $stmt->endfully, false );
         if ( ! $result->evaluable ) {
            $unevaluable = true;
         }
      }
      $this->scope->leave();

      $result = new Value();
      if ( ! $unevaluable ) {
         $result->evaluable = true;
      }
      --$this->loopDepth;
      return $result;
   }

   private function checkForLoop( \ForLoop $loop,
      StmtResult $stmtResult ): Value {
      ++$this->loopDepth;
      $this->scope->enter();

      $this->scope->enter( \SCOPE_CONDITION );
      $collection = $this->checkExpr( $loop->collection );
      $this->scope->leave();
      if ( ! $this->isIterable( $collection->type ) ) {
         $this->user->diag( DIAG_ERR, $loop->collection->pos,
            'cannot iterate over argument' );
         $this->user->bail();
      }
      $unevaluable = false;
      if ( ! $collection->evaluable ) {
         $unevaluable = true;
      }

      if ( $loop->item != null ) {
         $this->scope->bind( $loop->item->name, $loop->item );
         $loop->item->type = $this->getItemType( $collection->type );
      }

      $body = $this->checkBlockStmt( $loop->body, false );
      if ( ! $body->evaluable ) {
         $unevaluable = true;
      }
      $this->scope->leave();

      if ( $loop->endfully != null ) {
         $result = $this->checkBlockStmt( $loop->endfully, false );
         if ( ! $result->evaluable ) {
            $unevaluable = true;
         }
      }

      $result = new Value();
      if ( ! $unevaluable ) {
         $result->evaluable = true;
      }
      --$this->loopDepth;
      return $result;
   }

   private function isIterable( \Typing\Type $type ): bool {
      switch ( $this->describe( $type ) ) {
      case Desc::STRUCT:
         switch ( $type->structure->builtin ) {
         case BUILTIN_STRUCTURE_VEC:
            return true;
         }
         break;
      }
      return false;
   }

   private function getItemType( \Typing\Type $type ): \Typing\Type {
      switch ( $this->describe( $type ) ) {
      case Desc::STRUCT:
         switch ( $type->structure->builtin ) {
         case BUILTIN_STRUCTURE_VEC:
            return $type->args[ 0 ];
         }
         break;
      }
      throw new \Exception();
   }

   private function checkJump( \Jump $jump, StmtResult $result ): Value {
      if ( ! $this->isInsideLoop() ) {
         $this->err( $jump->pos,
            "%s outside loop", $jump->type == JUMP_BREAK ? "break" :
               "continue" );
      }
      $result = new Value();
      $result->evaluable = true;
      return $result;
   }

   private function isInsideloop(): bool {
      return ( $this->loopDepth > 0 );
   }

   private function checkReturnStmt( \ReturnStmt $stmt,
      StmtResult $result ): Value {
      $result = new Value();
      if ( $stmt->value !== null ) {
         $result = $this->checkExpr( $stmt->value );
         $this->checkReturnValue( $stmt->pos, $result );
      }
      else {
         if ( ! $this->isVoid( $this->func->returnType ) ) {
            $this->err( $stmt->pos,
               'missing return value in return statement' );
         }
         $result->evaluable = true;
      }
      $this->returning = true;
      return $result;
   }

   private function checkReturnValue( \Lexing\Position $pos,
      Value $value ): void {
      if ( $this->func === null ) {
         return;
      }
      if ( ! $this->isVoid( $this->func->returnType ) ) {
         //var_dump( $this->typeChecker->presentType( $value->type ) );
         //var_dump( $this->typeChecker->presentType( $this->func->returnType) );
         if ( ! $this->isInstanceOf( $value,
            $this->func->returnType ) ) {
            var_dump( 'got ' . $this->presentType( $value->type ) );
            var_dump( 'expected ' . $this->presentType(  $this->func->returnType ) );
            $this->err( $pos,
               'returning a value of the wrong type' );
         }

         if ( $value->binding !== null ) {
            $value->binding->value = new Value();
         }
      }
      else {
         if ( count( $this->func->returnParams ) == 0 ) {
            $this->err( $pos,
               'returning a value from a void function' );
         }
         foreach ( $this->func->returnParams as $param ) {
            /*
               if ( ! $this->typeChecker->isInstanceOf( $args[ $count ]->type,
                  $param->type ) ) {
                  $this->user->diag( DIAG_ERR, $call->args[ $count ]->pos,
                     "argument %d of wrong type", $count + 1 );
                  $this->user->bail();
               }
            */
         }
      }
   }

   private function checkDrop( \DropExpr $drop ): Value {
      foreach ( $drop->values as $value ) {
         $result = $this->checkExpr( $value->expr );
         if ( $result->binding !== null ) {
            if ( $result->binding->value === null ||
               $this->isVoid( $result->binding->value->type ) ) {
               $this->user->diag( DIAG_ERR, $value->expr->pos,
                  "attempting to drop an empty value" );
               $this->user->bail();
            }
            $result->binding->value = new Value();
         }
      }
      return new Value();
   }

   public function isInsideVirtExpr(): bool {
      return $this->insideVirtExpr;
   }

   public function checkTypeExpr( \TypeExpr $expr ): Type {
      $checker = new TypeExprChecker( $this->user, $this->typeChecker,
         $this->simpleExprChecker, $this->scope, $this->typeDescriber,
         $this->typePresenter, $this->samenessChecker, $this->evaluator,
         $this->moduleChecker, $this->task->builtinModule );
      return $checker->checkTypeExpr( $expr );
   }
}
