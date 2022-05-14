<?php

declare( strict_types = 1 );

namespace Checking;

use Ctce\Evaluator;
use Lexing\Position;
use Typing\AnonymousUnion;
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

class TypeExprChecker {
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
   private ?\Func $func;

   public function __construct( \User $user, \Typing\TypeChecker $typeChecker,
      SimpleExprChecker $simpleExprChecker, \Scope $scope,
      private Describer $typeDescriber,
      private Presenter $typePresenter,
      private SamenessChecker $samenessChecker,
      Evaluator $evaluator,
      private ModuleChecker $moduleChecker,
      private BuiltinModule $builtinModule ) {
      $this->user = $user;
      $this->typeChecker = $typeChecker;
      $this->simpleExprChecker = $simpleExprChecker;
      $this->scope = $scope;
      $this->loopDepth = 0;
      $this->returning = false;
      $this->insideVirtExpr = false;
      $this->func = null;
      $this->evaluator = $evaluator;
   }

   public function checkTypeExpr( \TypeExpr $expr ): Type {
      $union = new AnonymousUnion( $this->samenessChecker,
         $this->typeDescriber );
      foreach ( $expr->root->options as $option ) {
         $type = $this->checkPrefix( $option );
         $union->addMember( $type );
         /*
         if ( $option->diag !== null ) {
            $request->diag = $option->diag;
            break;
         }
         */
      }

      $type = $union->createType();

      return $type;
   }

   private function checkPrefix( \Node $node ): Type {
      if ( $node instanceof \Borrow ) {
         return $this->checkBorrow( $node );
      }
      else if ( $node instanceof \Unary ) {
         return $this->checkUnary( $node );
      }
      else {
         return $this->checkSuffix( $node );
      }
   }

   private function checkBorrow( \Borrow $borrow ): Type {
      $type = $this->checkSuffix( $borrow->operand );
      $type->borrowed = true;
      $type->mutable = $borrow->mutable;
      return $type;
   }

   private function checkUnary( \Unary $unary ): Type {
      $operand = $this->checkSuffix( $unary->operand );
      switch ( $unary->op ) {
      case UOP_MINUS:
         $operand->value = - $operand->value;
         return $operand;
      case UOP_PLUS:
         return $operand;
      case UOP_IMPORTANT:
         $operand->important = true;
         return $operand;
      default:
         UNREACHABLE();
      }
   }

   private function checkSuffix( \Node $node ): Type {
      if ( $node instanceof \TypeCall ) {
         return $this->checkCall( $node );
      }
      else if ( $node instanceof \Access ) {
         return $this->checkAccess( $node );
      }
      else {
         return $this->checkPrimary( $node );
      }
   }

   private function checkCall( \TypeCall $call ): Type {
      if ( $call->generic ) {
         return $this->checkGenericCall( $call );
      }
      else {
         $operand = $this->checkSuffix( $call->operand );
         var_dump( $this->describe( $operand ) );
         var_dump(  $operand->value );

         $args = [];
         $count = 0;
         foreach ( $call->args as $arg ) {
            $arg->type = $this->checkTypeExpr( $arg->expr );
            $args[] = $arg->type;
            ++$count;
         }

         if ( count( $args ) > 0 ) {
            foreach ( $call->args as $refinement ) {
               $member = $operand->structure->findMember( $refinement->name );
               if ( $member === null ) {
                  $this->user->diag( DIAG_ERR, $refinement->expr->pos,
                     "type has no member named `%s`", $refinement->name );
                  $this->user->bail();
               }

               /*
                           $result = $this->exprChecker->checkExpr( $value );
                           if ( ! $result->constant ) {
                              $this->user->diag( DIAG_ERR, $value->pos,
                                 "expected member argument must be constant" );
                              $this->user->bail();
                           }
               */

               //$refinement->type = $this->checkTypeExpr(
               //   $refinement->refinedTypeRequest );

               if ( $member->type !== null ) {
                  if ( ! $this->isCompatibleType( $refinement->type,
                     $member->type ) ) {
                     $this->user->diag( DIAG_ERR, $refinement->pos,
                        "expected member argument not a sub type of member" );
                     $this->user->bail();
                  }
               }

               if ( array_key_exists( $refinement->target,
                  $operand->refinements ) ) {
                  $this->user->diag( DIAG_ERR, $refinement->pos,
                     "`%s` already refined", $refinement->target );
                  $this->user->diag( DIAG_NOTICE, $operand
                     ->refinements[ $refinement->target ]->pos,
                     "`%s` was previously refined here", $refinement->target );
                  $this->user->bail();
               }

               $result = new Value();
               $result->type = $refinement->type;
               $operand->refinements[ $refinement->target ] = $result;
            }

            if ( count( $operand->refinements ) ===
               count( $operand->structure->members ) ) {
               $operand->constant = true;
            }
         }
      }
   }

   private function checkGenericCall( \TypeCall $call ): Type {
      $operand = $this->checkSuffix( $call->operand );

      switch ( $this->describe( $operand ) ) {
      case Desc::STRUCT:
         return $this->checkGenericStructCall( $call, $operand );
      }
      var_dump( $operand->structure->builtin );
      var_dump( $operand->structure->name );
      var_dump( $operand->structure->resolved );


/**/

      $value = $this->simpleExprChecker->expandGeneric( $generic, $args );
      return $value->inhabitant;
   }

   private function checkGenericStructCall( \TypeCall $call,
      Type $operand ): Type {

      $args = [];
      foreach ( $call->args as $expr ) {
         $type = $this->checkTypeExpr( $expr->expr );
         if ( $this->describe( $type ) === Description::INT ) {
            $arg = new Value();
            $arg->type = $type;
            $args[] = $arg;
         }
         else {
            $arg = new Value();
            $arg->type->structure = $this->builtinModule->typeStruct;
            $arg->type->spec = TYPESPEC_STRUCT_TYPE;
            $arg->inhabitant = $type;
            $args[] = $arg;
         }
      }

      switch ( $operand->structure->builtin ) {
      case BUILTIN_STRUCTURE_VEC:
         return $this->checkVecType( $call, $operand, $args );
      }
   }

   private function checkVecType( \TypeCall $call,
      Type $operand, array $args ): Type {
      $type = new Type();
      $type->structure = $operand->structure;
      $type->spec = TYPESPEC_STRUCT;
      /*
      foreach ( $option->args as $arg ) {
         if ( ! ( count( $arg->options ) === 1 &&
            $arg->options[ 0 ]->name === 'Any' ) ) {
            $argType = $this->checkTypeRequest( $arg );
            array_push( $type->args, $argType );
         }
      }*/
      return $type;
   }

   private function checkAccess( \Access $call ): Type {

   }

   private function checkPrimary( \Node $node ): Type {
      if ( $node instanceof \NameUsage ) {
         return $this->checkNameUsage( $node );
      }
      else if ( $node instanceof \TypeTuple ) {
         return $this->checkTypeTuple( $node );
      }
      else if ( $node instanceof \IntegerLiteral ) {
         return $this->checkIntegerLiteral( $node );
      }
      else if ( $node instanceof \StringLiteral ) {
         return $this->checkStringLiteral( $node );
      }
      else if ( $node instanceof \BoolLiteral ) {
         return $this->checkBoolLiteral( $node );
      }
      else if ( $node instanceof \Structure ) {
         return $this->checkStructLiteral( $node );
      }
      else {
         UNREACHABLE();
      }
   }

   private function checkNameUsage( \NameUsage $usage ): Type {
      $binding = $this->scope->get( $usage->name, $usage->pos );

      if ( $binding === null ) {
         return $this->err( $usage->pos,
            'unknown type `%s`', $usage->name );
      }

      $object = $binding->node;
      if ( $object instanceof \ImportItem ) {
         $object = $object->object;
      }

      if ( $binding->value->type->spec === TYPESPEC_UNRESOLVED ) {
         $this->moduleChecker->checkItemOutOfOrder( $binding->module,
            $binding->node );
         $binding->value = null;
         $binding = $this->scope->get( $usage->name, $usage->pos );
      }

      $value = $binding->value;

      $usage->object = $object;
      $usage->value = clone $value;

      $type = new \Typing\Type();
      $type->name = $usage->name;

      if ( $object instanceof \Constant ) {
         $type->value = $object;
         $type->spec = TYPESPEC_VALUE;
      }
      else if ( $object instanceof \Let ) {
         $type->value = $binding->value;
         $type->spec = TYPESPEC_VALUE;
      }
      else if ( $object instanceof \Param ) {
         if ( $object->rebindable ) {
            $this->user->diag( DIAG_ERR, $usage->pos,
               "cannot use mutable binding as type" );
            $this->user->bail();
         }
         $type->spec = TYPESPEC_VALUE;
         $type->value = $binding->value;
         //$type->value = $object;
      /*
         $type->spec = $object->type->spec;
         $type->structure = $object->type->structure;
         $type->value = $object->value;
      */
      }
      else if ( $object instanceof \TypeParam ) {
         $type = $object->type;
      }
      else if ( $object instanceof \Enumeration ) {
         $type->spec = TYPESPEC_ENUM;
         $type->enumeration = $object;
      }
      else if ( $object instanceof \TraitObj ) {
         $type->spec = TYPESPEC_TRAIT;
         $type->trait = $object;
      }
      else if ( $object instanceof \Structure ) {
         $type->spec = TYPESPEC_STRUCT;
         $type->structure = $object;
         switch ( $object->builtin ) {
         case BUILTIN_STRUCTURE_INT:
         case BUILTIN_STRUCTURE_BOOL:
         case BUILTIN_STRUCTURE_STR:
            #$type->borrowed = true;
            break;
         }
      }
      else if ( $object instanceof \TypeAlias ) {
         $type = clone $object->type;
      }
      else if ( $object instanceof \Generic ) {
         $type = $this->resolveGenericType( $object, $args );
      }
      else {
         UNREACHABLE();
      }

      return $type;
   }

   private function checkTypeTuple( \TypeTuple $tuple ): Type {
      // Tuples that consist solely of one unnamed element decay into the
      // element that they contain.
      if ( count( $tuple->args ) === 1 && $tuple->args[ 0 ]->name === '' ) {
         return $this->checkTypeExpr( $tuple->args[ 0 ]->expr );
      }

      $structure = new \Structure();

      foreach ( $tuple->args as $arg ) {
         $type = $this->checkTypeExpr( $arg->expr );
         $member = new \StructureMember();
         $member->name = $arg->name;
         $member->type = $type;
         $member->visible = true;
         $structure->members[] = $member;
      }

      if ( count( $structure->members ) > 0 ) {
         $different = false;
         $firstMember = $structure->members[ 0 ];
         for ( $i = 1; $i < count( $structure->members ); ++$i ) {
            if ( ! $this->isSameType( $structure->members[ $i ]->type,
               $firstMember->type ) ) {
               $different = true;
               break;
            }
         }
         $structure->homogeneous = ( $different === false );
      }

      $structure->defined = true;

      $type = new Type();
      $type->structure = $structure;
      $type->spec = TYPESPEC_STRUCT;

      $this->moduleChecker->appendTupleStruct( $structure );

      return $type;
   }

   private function determineHomogeneous( \Structure $structure ): void {
      if ( count( $structure->members ) > 0 ) {
         $different = false;
         $firstMember = $structure->members[ 0 ];
         for ( $i = 1; $i < count( $structure->members ); ++$i ) {
            if ( ! $this->isSameType( $structure->members[ $i ]->type,
               $firstMember->type ) ) {
               $different = true;
               break;
            }
         }
         $structure->homogeneous = ( ! $different );
      }
   }

   private function checkIntegerLiteral( \IntegerLiteral $literal ): Type {
      $type = new Type();
      $type->spec = TYPESPEC_STRUCT;
      $type->structure = $this->typeChecker->getIntStruct();
      $type->value = $literal->value;
      return $type;
   }

   private function checkStringLiteral( \StringLiteral $literal ): Type {
      $type = new Type();
      $type->spec = TYPESPEC_STRUCT;
      $type->structure = $this->typeChecker->getStrStruct();
      $type->value = $literal->index;
      return $type;
   }

   private function checkBoolLiteral( \BoolLiteral $literal ): Type {
      $type = new Type();
      $type->spec = TYPESPEC_STRUCT;
      $type->structure = $this->typeChecker->getBoolStruct();
      $type->value = $literal->value;
      return $type;
   }

   public function checkStructLiteral( \Structure $structure ): Type {
      $this->declChecker->checkStructLiteral( $structure );
      $result = new Value();
      $result->type->structure = $structure;
      $result->type->spec = TYPESPEC_STRUCT_TYPE;
      $result->constant = true;
      $result->evaluable = true;
      return $result;
   }

   private function err( Position $pos, string $message, ... $args ): never {
      $result = new Value();
      $result->type->spec = TYPESPEC_ERR;
      $result->diag = $this->user->diag( DIAG_ERR, $pos, $message,
         ... $args );
      throw new CheckErr( $result );
   }
}
