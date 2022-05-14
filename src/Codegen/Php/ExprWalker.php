<?php

declare( strict_types = 1 );

namespace Codegen\Php;

use Binary;
use Typing\TypeChecker;

class ExprWalker {
   private \Task $task;
   private PhpScript $script;
   private ?PhpExpr $phpExpr;
   private ScopeList $scopeList;
   private TypeChecker $typeChecker;

   public function __construct( \Task $task, PhpScript $script,
      ScopeList $scopeList, TypeChecker $typeChecker ) {
      $this->task = $task;
      $this->script = $script;
      $this->phpExpr = null;
      $this->scopeList = $scopeList;
      $this->typeChecker = $typeChecker;
   }

   public function visitExpr( \Expr $expr ): PhpExpr {
      return $this->visitNonConstantExpr( $expr );
      if ( $expr->constant ) {
         return $this->visitConstantExpr( $expr );
      }
      else {
         return $this->visitNonConstantExpr( $expr );
      }
   }

   private function visitConstantExpr( \Expr $expr ): PhpExpr {
      $phpExpr = new PhpExpr();
      $phpLiteral = new PhpIntegerLiteral();
      $phpLiteral->value = 0;
      $phpExpr->root = $phpLiteral;
      if ( $expr->type->structure && $expr->type->structure->name == 'Int' ) {
         $phpLiteral->value = $expr->value;
      }
      return $phpExpr;
   }

   private function visitNonConstantExpr( \Expr $expr ): PhpExpr {
      $prev = $this->phpExpr;
      $this->phpExpr = new PhpExpr();
      $result = $this->visitExprRoot( $expr->root );
      if ( $result->node instanceof PhpIfStmt ) {
         $this->phpExpr->returnsValue = true;
      }
      $this->phpExpr->root = $result->node;
      #$this->phpExpr->alloc = $result->alloc;
      $phpExpr = $this->phpExpr;
      $this->phpExpr = $prev;

      $this->setExprType( $phpExpr, $expr->type );

      #foreach ( $cExpr->allocs as $alloc ) {
      #   array_push( $this->scopeList->scope->allocs, $alloc );
      #}

      return $phpExpr;
   }

   private function setExprType( PhpExpr $phpExpr, \Typing\Type $type ): void {
      switch ( $type->spec ) {
      case TYPESPEC_STR:
         $phpExpr->type = PHP_TYPE_STRING;
         break;
      case TYPESPEC_STRUCT:
         switch ( $type->structure->name ) {
         case 'Int':
            $phpExpr->type = PHP_TYPE_INT;
            break;
         case 'Bool':
            $phpExpr->type = PHP_TYPE_BOOL;
            break;
         case 'Str':
            $phpExpr->type = PHP_TYPE_STRING;
            break;
         default:
            $cExpr->type = SPEC_STRUCTPTR;
            $cExpr->struct = $this->task->bundlesToStructs[
            $type->structure->name ];
            break;
         }
         break;
      case TYPESPEC_ENUM:
         //$this->setExprType( $cExpr, $type->enumeration->baseType );
         break;
      }
   }

   private function visitExprRoot( \Node $node ): Result {
      switch ( $node->nodeType ) {
      case \NODE_LET:
         return $this->visitLocalBinding( $node );
      case \NODE_BLOCKSTMT:
         return $this->visitBlock( $node );
      case \NODE_IF:
         $result = $this->visitIf( $node );
         $wrapper = new PhpStmtWrapper();
         $wrapper->stmt = $result->node;
         $result->node = $wrapper;
         return $result;
      case NODE_ASSIGNMENT:
         return $this->visitAssignment( $node );
      case \NODE_BINARY:
         return $this->visitBinary( $node );
      case NODE_LOGICAL:
         return $this->visitLogical( $node );
      default:
         return $this->visitPrefix( $node );
      }
   }

   private function visitLocalBinding( \Let $localBinding ): Result {
      $var = new PhpVar();
      $var->value = $this->visitExpr( $localBinding->value );
      $binding = $this->scopeList->get( $localBinding->name );
      $binding->var = $var;

      #$binding->alloc = $result->alloc;
      $result = new Result();
      $result->node = $var;
      // $result->node = $result->alloc;

      #$result->binding2 = $this->scopeList->get( $binding->name );
      #$result->binding2->alloc = $result->alloc;

/*
      if ( $result->alloc != null ) {
         $result->binding2->alloc = $result->alloc;
         ++$result->alloc->numLabelsAttached;
      }
      else {
         /*
            $alloc = new CAlloc();
            $alloc->struct = $this->task->bundlesToStructs[
               $result->member->type->bundle->name ];
            array_push( $this->cExpr->allocs, $alloc );
            $alloc->skipMalloc = true;
            $alloc->externalRefs = true;
            $expr = new CExpr();
            $expr->root = $result->node;
            $alloc->initializer = $expr;
            $result->binding2->alloc = $alloc;
            ++$alloc->numLabelsAttached;
            $alloc->borrowed = true;

      }
      */

      //if ( isset( $result->binding->assignment ) ) {
      //   $result->node = $result->binding->assignment;
      //}

      return $result;
   }

   public function visitBlockStmt( \BlockStmt $stmt ): PhpBlockStmt {
      #$this->scopeList->push();
      $phpStmt = new PhpBlockStmt();
      foreach ( $stmt->stmts as $childStmt ) {
         $childPhpStmt = $this->visitStmt( $childStmt );
         if ( $childStmt->yield ) {
            $returnValue = new PhpStmtReturnValue();
            $returnValue->value = $childPhpStmt;
            $phpStmt->returnValue = $returnValue;
            $childPhpStmt = $returnValue;
         }
         array_push( $phpStmt->items, $childPhpStmt );
         if ( $childStmt->nodeType == NODE_ERR_STMT ) {
            break;
         }
      }
      #$scope = $this->scopeList->pop();
      #$this->addCleanup( $cStmt, $scope );
      return $phpStmt;
   }

   public function visitBlock( \BlockStmt $block ): Result {
      $stmt = $this->visitBlockStmt( $block );
      $stmt->returnValue->var = new PhpVar();
      $result = new Result();
      $result->node = $stmt;
      $result->returnValue = new PhpVar();
      if ( $block->returnValueExprStmt != null ) {
         #$returnValue = new PhpVar();
         //$result->returnValue =
      }
      return $result;
   }

   private function visitStmt( \Node $stmt ): PhpNode {
      if ( $stmt instanceof \ExprStmt ) {
         return $this->visitExprStmt( $stmt );
      }
      else {
         throw new \Exception();
      }
   }

   private function visitExprStmt( \ExprStmt $stmt ): PhpNode {
      $result = $this->visitExpr( $stmt->expr );
      $phpStmt = new PhpExprStmt();
      $phpStmt->expr = $result;
      return $phpStmt;
   }

   private function visitIf( \IfStmt $stmt ): Result {
      $phpStmt = new PhpIfStmt();
      $phpStmt->cond = $this->visitExpr( $stmt->cond );
      $phpStmt->body = $this->visitBlockStmt( $stmt->body );
      $phpStmt->body->returnValue =
      $topStmt = $phpStmt;
      /*
      foreach ( $stmt->elifs as $elif ) {
         $childStmt = new PhpIfStmt();
         $childStmt->cond = $this->visitExpr( $elif->cond );
         $childStmt->body = $this->visitBlockStmt( $elif->body );
         $phpStmt->else = $childStmt;
         $phpStmt = $childStmt;
      }
      if ( $stmt->elseBody !== null ) {
         $phpStmt->else = $this->visitBlockStmt( $stmt->elseBody );
      }*/
      $result = new Result();
      $result->node = $topStmt;
      return $result;
   }

   private function visitBinary( Binary $binary ): Result {
      switch ( $this->typeChecker->describe( $binary->type ) ) {
      case \Typing\DESC_INT:
         return $this->visitBinaryInt( $binary );
      default:
         UNREACHABLE();
      }
   }

   private function visitBinaryInt( Binary $binary ): Result {
      $lside = $this->visitExprRoot( $binary->lside );
      $rside = $this->visitExprRoot( $binary->rside );
      $phpBinary = new PhpBinary();
      $phpBinary->lside = $lside->node;
      $phpBinary->rside = $rside->node;
      $phpBinary->op = match ( $binary->op ) {
         Binary::OP_EQ => PHP_BINARY_EQ,
         Binary::OP_NEQ => PHP_BINARY_NEQ,
         Binary::OP_LT => PHP_BINARY_LT,
         Binary::OP_LTE => PHP_BINARY_LTE,
         Binary::OP_GT => PHP_BINARY_GT,
         Binary::OP_GTE => PHP_BINARY_GTE,
         Binary::OP_ADD => PHP_BINARY_ADD,
         Binary::OP_SUB => PHP_BINARY_SUB,
         Binary::OP_MUL => PHP_BINARY_MUL,
         Binary::OP_DIV => PHP_BINARY_DIV,
         Binary::OP_MOD => PHP_BINARY_MOD,
         default => \UNREACHABLE(),
      };
      $phpBinary->result = new PhpVar();
      $result = new Result();
      $result->node = $phpBinary;
      $result->returnValue = $phpBinary->result;
      return $result;
   }

   private function visitPrefix( \Node $node ): Result {
      switch ( $node->nodeType ) {
      case \NODE_UNARY:
         return $this->visitUnary( $node );
      case \NODE_LOGICAL_NOT:
         return $this->visitLogicalNot( $node );
      default:
         return $this->visitSuffix( $node );
      }
   }

   private function visitSuffix( \Node $node ): Result {
      switch ( $node->nodeType ) {
      case NODE_ACCESS:
         return $this->visitAccess( $node );
      case NODE_SUBSCRIPT:
         return $this->visitSubscript( $node );
      case \NODE_CALL:
         return $this->visitCall( $node );
      default:
         return $this->visitPrimary( $node );
      }
   }

   private function visitCall( \Call $call ): Result {
      switch ( $call->type ) {
      case \CALL_ENUM:
         return $this->visitEnumCall( $call );
      case \CALL_STRUCTURE:
         return $this->visitBundleCall( $call );
      case \CALL_FUNC:
         return $this->visitFuncCall( $call );
      case \CALL_TRAIT:
         return $this->visitTraitCall( $call );
      }
   }

   private function visitFuncCall( \Call $call ): Result {
      if ( $call->func->virtual ) {
         return $this->visitVirtFuncCall( $call );
      }
      else {
         return $this->visitUserFuncCall( $call );
      }
   }

   private function visitUserFuncCall( \Call $call ): Result {
      $phpCall = new PhpCall();
      $phpCall->func = PHP_CALL_USER;

      switch ( $call->func->builtin ) {
      case \Func::BUILTIN_PRINTLN:
         $phpCall->func = PHP_CALL_PRINTF;
         break;
      default:
         $operand = $this->visitSuffix( $call->operand );
         $funcName = $call->func->name;
         if ( $operand->method ) {
            $funcName = $operand->bundle->name . 'x' . $call->func->name;
         }
         $phpCall->userFunc = $this->script->funcsToPhpfuncs[ $funcName ];
      }

      $args = [];
      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $arg );
         array_push( $args, $result );
      }
      $phpCall->args = $args;

      /*
      // Arguments.
      $count = 0;
      $addedSelf = false;
      foreach ( $call->func->params as $param ) {
         if ( ! $addedSelf && ( $operand->method || $operand->trait ) ) {
            $expr = new CExpr();
            $expr->root = $operand->node;
            array_push( $cCall->args, $expr );
            $addedSelf = true;
         }
         else if ( $count < count( $call->args ) ) {
            $expr = $this->visitExpr( $call->args[ $count ] );

            if ( $param->type->spec == TYPESPEC_TRAIT ) {
               $alloc = new CAlloc();
               $alloc->struct = $this->task->bundlesToStructs[
               $param->type->trait->name ];
               array_push( $this->cExpr->allocs, $alloc );
               $alloc->stack = true;

               $initz = new CAllocInitializer();
               $initz->member = 'interface';
               $nameUsage = new CNameUsage();
               $nameUsage->name = $call->args[ $count ]->type->bundle->name . 'x' .
                  $param->type->trait->name . 'Impl';
               $addrof = new CUnary();
               $addrof->op = CUOP_ADDROF;
               $addrof->operand = $nameUsage;
               $valueExpr = new CExpr();
               $valueExpr->root = $addrof;
               $initz->value = $valueExpr;
               array_push( $alloc->initializers, $initz );

               $initz = new CAllocInitializer();
               $initz->member = 'object';
               $nameUsage = new CNameUsage();
               $nameUsage->name = 'object';
               $initz->value = $expr;
               array_push( $alloc->initializers, $initz );

               $expr = new CExpr();
               $expr->root = $alloc;
               array_push( $cCall->args, $expr );

            }
            else if ( $param->type->spec == TYPESPEC_BUNDLE &&
               ! $this->isPrimitiveBundle( $param->type->bundle ) ) {
               $expr->alloc->externalRefs = true;
               array_push( $cCall->args, $expr );
            }
            else {
               array_push( $cCall->args, $expr );
            }

            $this->cExpr->allocs = array_merge( $this->cExpr->allocs,
               $expr->allocs );
            $expr->allocs = [];
            ++$count;
         }
         else {
            $expr = $this->visitExpr( $param->defaultArg );
            array_push( $cCall->args, $expr );
            ++$count;
         }
      } */

      $result = new Result();

      if ( $call->func->returnType &&
         $call->func->returnType->spec == TYPESPEC_STRUCT &&
         ! $this->isPrimitiveBundle( $call->func->returnType->structure ) ) {
         $alloc = new CAlloc();
         $alloc->skipMalloc = true;
         $alloc->externalRefs = true;
         // We assume the returned allocation is referenced somewhere else
         // besides this function call.
         //$alloc->refCount = 1;
         $alloc->struct = $this->task->bundlesToStructs[
         $call->func->returnType->structure->name ];
         array_push( $this->cExpr->allocs, $alloc );
         $expr = new CExpr();
         $expr->root = $cCall;
         $alloc->initializer = $expr;
         $result->alloc = $alloc;
         $result->node = $alloc;
      }
      else {
         $result->node = $phpCall;
      }

      return $result;
   }

   private function visitPrimary( \Node $node ): Result {
      switch ( $node->nodeType ) {
      case \NODE_NULL_POINTER:
         return $this->visitNullPointer();
      case \NODE_POINTER_CONSTRUCTOR:
         return $this->visitPointerConstructor( $node );
      case \NODE_SIZEOF:
         return $this->visitSizeof( $node );
      case \NODE_NAME_USAGE:
         return $this->visitNameUsage( $node );
      case \NODE_STRUCTURE_LITERAL:
         return $this->visitBundleLiteral( $node );
      case \NODE_INTEGER_LITERAL:
         return $this->visitIntegerLiteral( $node );
      case NODE_BOOL_LITERAL:
         return $this->visitBoolLiteral( $node );
      case \NODE_STRING_LITERAL:
         return $this->visitStringLiteral( $node );
      case \NODE_TUPLE:
         return $this->visitParen( $node );
      default:
         UNREACHABLE( "unhandled node: %d", $node->nodeType );
      }
      return new Result;
   }

   private function visitNameUsage( \NameUsage $usage ): Result {
      switch ( $usage->object->nodeType ) {
      case \NODE_LET:
         return $this->visitLocalBindingUsage( $usage );
      case \NODE_CONSTANT:
         return $this->visitConstant( $usage->object );
      case \NODE_ENUMERATOR:
         return $this->visitEnumerator( $usage->object );
      case \NODE_VAR:
      case \NODE_PARAM:
         return $this->visitParam( $usage, $usage->object );
      case \NODE_FUNC:
         return $this->visitFuncUsage( $usage );
      default:
         UNREACHABLE();
      }
   }

   private function visitLocalBindingUsage( \NameUsage $usage ): Result {
      $binding = $this->scopeList->get( $usage->name );
      $varUsage = new PhpVarUsage();
      $varUsage->var = $binding->var;
      $result = new Result();
      $result->node = $varUsage;
      return $result;
   }

   private function visitFuncUsage( \NameUsage $usage ): Result {
      $result = new Result();
      $result->func = $this->script->funcsToPhpfuncs[ $usage->object->name ];
      return $result;
   }

   private function visitIntegerLiteral( \IntegerLiteral $literal ): Result {
      #$cell = $this->machine->set( $literal->value );


      $phpLiteral = new PhpIntegerLiteral();
      $phpLiteral->value = $literal->value;

      /*
            $cAssignment = new CAssignment();
            $cAssignment->value = $cLiteral;
            $cAssignment->type = RUNTIMEVALUE_INT;
            $cAssignment->var = $this->allocLocalVar();
      */
      $result = new Result();
      $result->node = $phpLiteral;
      return $result;
   }

   private function visitBoolLiteral( \BoolLiteral $literal ): Result {
      $phpLiteral = new PhpBoolLiteral();
      $phpLiteral->value = ( $literal->value != 0 );
      $result = new Result();
      $result->node = $phpLiteral;
      return $result;
   }

   private function visitStringLiteral( \StringLiteral $literal ): Result {
      $phpLiteral = new PhpStringLiteral();
      $phpLiteral->value = $literal->value;
      $result = new Result();
      $result->node = $phpLiteral;
      return $result;
   }

   private function visitParen( \Tuple $paren ): Result {
      $phpParen = new PhpParen();
      $phpParen->expr = $this->visitExpr( $paren->expr );
      $result = new Result();
      $result->node = $phpParen;
      return $result;
   }
}
