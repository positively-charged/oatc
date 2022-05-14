<?php

declare( strict_types = 1 );

namespace Codegen\Oatir;

use Binary;
use Typing\TypeChecker;

class ExprWalker {
   private \Task $task;
   private Archive $archive;
   private ScopeList $scopeList;
   private TypeChecker $typeChecker;

   public function __construct( \Task $task, Archive $archive,
      ScopeList $scopeList, TypeChecker $typeChecker ) {
      $this->task = $task;
      $this->archive = $archive;
      $this->scopeList = $scopeList;
      $this->typeChecker = $typeChecker;
   }

   public function visitBlockStmt( Runner $runner, \BlockStmt $stmt ): void {
      foreach ( $stmt->stmts as $childStmt ) {
         $this->visitStmt( $runner, $childStmt );
      }
   }

   private function visitStmt( Runner $runner, \Node $stmt ): void {
      if ( $stmt instanceof \ExprStmt ) {
         $this->visitExprStmt( $runner, $stmt );
      }
      else {
         UNREACHABLE();
      }
   }

   private function visitExprStmt( Runner $runner,
      \ExprStmt $stmt ): void {
      $result = $this->visitExpr( $runner, $stmt->expr );
   }

   private function visitExpr( Runner $runner, \Expr $expr ): Result {
      return $this->visitExprRoot( $runner, $expr->root );
   }

   private function visitExprRoot( Runner $runner,
      \Node $node ): Result {
      if ( $node instanceof \IfStmt ) {
         return $this->visitIf( $runner, $node );
      }
      else if ( $node instanceof \ReturnStmt ) {
         return $this->visitReturn( $runner, $node );
      }
      else if ( $node instanceof \Binary ) {
         return $this->visitBinary( $runner, $node );
      }
      else {
         return $this->visitPrefix( $runner, $node );
      }
   }

   private function visitIf( Runner $runner, \IfStmt $stmt ): Result {
      $expr = $runner->addBlock();
      $cond = $this->visitExpr( $runner, $stmt->cond );
      $body = $runner->addBlock();
      $this->visitBlockStmt( $runner, $stmt->body );
      $exit = $runner->addBlock();
      $elseBody = $exit;
      if ( $stmt->elseBody !== null ) {
         $elseBody = $runner->addBlock();
         $this->visitBlockStmt( $runner, $stmt->elseBody );
         $runner->jumpToBlock( $elseBody, $exit );
      }
      $runner->CondJumpToBlock( $cond->slot, $expr, $body, $elseBody );
      $runner->jumpToBlock( $body, $exit );
      $runner->changeBlock( $exit );
   /*
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
      return $result;
   }

   private function visitReturn( Runner $runner,
      \ReturnStmt $stmt ): Result {
      $instruction = new RetInstruction();
      if ( $stmt->value != null ) {
         $result = $this->visitExpr( $runner, $stmt->value );
         $instruction->value = $result->slot;
      }
      $runner->appendInstruction( $instruction );
      $result = new Result();
      return $result;
   }

   private function visitBinary( Runner $runner, Binary $binary ): Result {
      switch ( $this->typeChecker->describe( $binary->type ) ) {
      case \Typing\DESC_INT:
         return $this->visitBinaryInt( $runner, $binary );
      default:
         UNREACHABLE();
      }
   }

   private function visitBinaryInt( Runner $runner, Binary $binary ): Result {
      $lside = $this->visitExprRoot( $runner, $binary->lside );
      $rside = $this->visitExprRoot( $runner, $binary->rside );

      $opcode = match ( $binary->op ) {
         Binary::OP_EQ => OP_EQ,
         Binary::OP_NEQ => OP_NEQ,
         Binary::OP_LT => OP_LT,
         Binary::OP_LTE => OP_LTE,
         Binary::OP_GT => OP_GT,
         Binary::OP_GTE => OP_GTE,
         Binary::OP_ADD => OP_ADD,
         Binary::OP_SUB => OP_SUB,
         Binary::OP_MUL => OP_MUL,
         Binary::OP_DIV => OP_DIV,
         Binary::OP_MOD => OP_MOD,
         default => \UNREACHABLE(),
      };

      $instruction = new BinaryInstruction( $opcode );
      $instruction->lside = $lside->slot;
      $instruction->rside = $rside->slot;
      $instruction->result = $runner->allocSlot();
      $runner->appendInstruction( $instruction );
      $result = new Result();
      $result->slot = $instruction->result;
      return $result;
   }

   private function visitPrefix( Runner $runner,
      \Node $node ): Result {
      switch ( $node->nodeType ) {
      case \NODE_UNARY:
         return $this->visitUnary( $node );
      case \NODE_LOGICAL_NOT:
         return $this->visitLogicalNot( $node );
      default:
      }
      return $this->visitSuffix( $runner, $node );
   }

   private function visitSuffix( Runner $runner,
      \Node $node ): Result {
      switch ( $node->nodeType ) {
      case NODE_ACCESS:
         return $this->visitAccess( $node );
      case NODE_SUBSCRIPT:
         return $this->visitSubscript( $node );
      case \NODE_CALL:
         return $this->visitCall( $runner, $node );
      default:
         return $this->visitPrimary( $runner, $node );
      }
   }

   private function visitCall( Runner $runner, \Call $call ): Result {
      switch ( $call->type ) {
      case \CALL_ENUM:
         return $this->visitEnumCall( $call );
      case \CALL_STRUCTURE:
         return $this->visitBundleCall( $call );
      case \CALL_FUNC:
         return $this->visitFuncCall( $runner, $call );
      case \CALL_TRAIT:
         return $this->visitTraitCall( $call );
      }
   }

   private function visitFuncCall( Runner $runner, \Call $call ): Result {
      if ( $call->func->virtual ) {
         return $this->visitVirtFuncCall( $call );
      }
      else {
         return $this->visitUserFuncCall( $runner, $call );
      }
   }

   private function visitUserFuncCall( Runner $runner, \Call $call ): Result {
      switch ( $call->func->builtin ) {
      case \Func::BUILTIN_PRINTLN:
         return $this->callPrintln( $runner, $call );
      default:
         break;
      }
      $instruction = new CallInstruction();

      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $runner, $arg );
         array_push( $instruction->args, $result->slot );
      }

      switch ( $call->func->builtin ) {
      case \Func::BUILTIN_PRINTLN:
         $instruction->func = $this->archive->printf;
         break;
      default:
         $operand = $this->visitSuffix( $runner, $call->operand );
         $funcName = $call->func->name;
         if ( $operand->method ) {
            $funcName = $operand->structure->name . 'x' . $call->func->name;
         }

         $instruction->func = $runner->findFunc( $call->func );

         #$instruction->userFunc = $this->script->funcsToPhpfuncs[ $funcName ];
      }

      $runner->appendInstruction( $instruction );

/*
      $args = [];
      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $runner, $arg );
         array_push( $args, $result );
      }
      $instruction->args = $args;
*/

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

      if ( $call->func->returnType !== null ) {
         $returnValue = $runner->allocSlot();
         $instruction->returnValue = $returnValue;
         $result->slot = $returnValue;
      }

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
         //$result->node = $phpCall;
      }

      return $result;
   }

   private function callPrintln( Runner $runner, \Call $call ): Result {
      $format = '';
      $args = [];
      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $runner, $arg );
         switch ( $this->typeChecker->describe( $arg->type ) ) {
         case \Typing\DESC_INT:
         case \Typing\DESC_BOOL:
            $format .= '%d';
            array_push( $args, $result->slot );
            break;
         case \Typing\DESC_BOOL:
            $t = $this->addString( 'true' );
            $f = $this->addString( 'false' );
            $slot = $this->passBoolArg( $runner, $result );
            array_push( $args, $slot );
            $format .= '%s';
            break;
         case \Typing\DESC_STR:
            $format .= '%s';
            array_push( $args, $result->slot );
            break;
         default:
            UNREACHABLE();
         }
      }
      $format .= "\n";

      $string = $this->addString( $format );
      $instruction = new SetStrInstruction();
      $instruction->string = $string;
      $instruction->destination = $runner->allocSlot();
      $instruction->destination->type = TYPE_ISIZE;
      $runner->appendInstruction( $instruction );
      array_unshift( $args, $instruction->destination );

      $instruction = new CallInstruction();
      $instruction->func = $this->archive->printf;
      $instruction->args = $args;
      $runner->appendInstruction( $instruction );
      $result = new Result();
      return $result;
   }

   private function passBoolArg( Runner $runner, Result $result ): Slot {
      $expr = $runner->addBlock();
      $cond = $this->visitExpr( $runner, $stmt->cond );
      $body = $runner->addBlock();
      $this->visitBlockStmt( $runner, $stmt->body );
      $exit = $runner->addBlock();
      $elseBody = $exit;
      if ( $stmt->elseBody !== null ) {
         $elseBody = $runner->addBlock();
         $this->visitBlockStmt( $runner, $stmt->elseBody );
         $runner->jumpToBlock( $elseBody, $exit );
      }
      $runner->CondJumpToBlock( $cond->slot, $expr, $body, $elseBody );
      $runner->jumpToBlock( $body, $exit );
      $runner->changeBlock( $exit );
   }

   private function visitPrimary( Runner $runner,
      \Node $node ): Result {
      switch ( $node->nodeType ) {
      case \NODE_NULL_POINTER:
         return $this->visitNullPointer();
      case \NODE_POINTER_CONSTRUCTOR:
         return $this->visitPointerConstructor( $node );
      case \NODE_SIZEOF:
         return $this->visitSizeof( $node );
      case \NODE_NAME_USAGE:
         return $this->visitNameUsage( $runner, $node );
      case \NODE_STRUCTURE_LITERAL:
         return $this->visitBundleLiteral( $node );
      case \NODE_INTEGER_LITERAL:
         return $this->visitIntegerLiteral( $runner, $node );
      case \NODE_BOOL_LITERAL:
         return $this->visitBoolLiteral( $runner, $node );
      case \NODE_STRING_LITERAL:
         return $this->visitStringLiteral( $runner, $node );
      case \NODE_TUPLE:
         return $this->visitParen( $node );
      default:
         UNREACHABLE( "unhandled node: %d", $node->nodeType );
      }
      return new Result;
   }

   private function visitNameUsage( Runner $runner,
      \NameUsage $usage ): Result {
      switch ( $usage->object->nodeType ) {
      case \NODE_LET:
         return $this->visitLocalBindingUsage( $runner, $usage );
      case \NODE_CONSTANT:
         return $this->visitConstant( $usage->object );
      case \NODE_ENUMERATOR:
         return $this->visitEnumerator( $usage->object );
      case \NODE_VAR:
      case \NODE_PARAM:
         return $this->visitParam( $usage, $usage->object );
      case \NODE_FUNC:
         return new Result();
         break;
      default:
         UNREACHABLE();
      }
   }

   private function visitLocalBindingUsage( Runner $runner,
      \NameUsage $usage ): Result {
      $binding = $this->scopeList->get( $usage->name );
      $result = new Result();
      $result->slot = $binding->slot;
      return $result;
   }

   private function visitIntegerLiteral( Runner $runner,
      \IntegerLiteral $literal ): Result {
      $result = new Result();
      $result->slot = $runner->setImm( $literal->value );
      return $result;
   }

   private function visitBoolLiteral( Runner $runner,
      \BoolLiteral $literal ): Result {
      $result = new Result();
      $result->slot = $runner->setImm( $literal->value );
      return $result;
   }

   private function visitStringLiteral( Runner $runner,
      \StringLiteral $literal ): Result {
      $string = $this->addString( $literal->value );
      $instruction = new SetStrInstruction();
      $instruction->string = $string;
      $instruction->destination = $runner->allocSlot();
      $instruction->destination->type = TYPE_ISIZE;
      $runner->appendInstruction( $instruction );
      $result = new Result();
      $result->slot = $instruction->destination;
      return $result;
   }

   private function addString( string $value ): IndexedString {
      foreach ( $this->archive->strings as $string ) {
         if ( $string->value == $value ) {
            return $string;
         }
      }
      $string = new IndexedString();
      $string->value = $value;
      $string->index = count( $this->archive->strings );
      array_push( $this->archive->strings, $string );
      return $string;
   }

   private function isPrimitiveBundle( \Structure $bundle ): bool {
      switch ( $bundle->name ) {
      case 'Int':
      case 'Bool':
         return true;
      default:
         return false;
      }
   }
}
