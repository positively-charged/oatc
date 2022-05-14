<?php

declare( strict_types = 1 );

namespace Codegen\X64;

use Binary;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description as Desc;
use Typing\TypeChecker;

class ExprWalker {
   use DescriberUsage;

   private \Task $task;
   private Assembly $assembly;
   private ScopeList $scopeList;
   private TypeChecker $typeChecker;
   private array $condSections;
   private array $exitSections;

   public function __construct( \Task $task, Assembly $assembly,
      ScopeList $scopeList, TypeChecker $typeChecker,
      private Machine $machine,
      private Describer $typeDescriber ) {
      $this->task = $task;
      $this->assembly = $assembly;
      $this->scopeList = $scopeList;
      $this->typeChecker = $typeChecker;
      $this->condSections = [];
      $this->exitSections = [];
   }

   public function visitFuncBlockStmt( \BlockStmt $stmt ): void {
      $result = $this->visitBlockStmt( $stmt );
      if ( $result->value !== null ) {
         $this->machine->getReturnValue( $result->value );
      /*
         $instruction = new RetInstruction();
         $instruction->value = $result->reg;
         $runner->appendInstruction( $instruction );
      */
      }
   }

   public function visitBlockStmt( \BlockStmt $stmt ): Result {
      $result = new Result();
      foreach ( $stmt->stmts as $childStmt ) {
         $result = $this->visitStmt( $childStmt );
         /*
         if ( $runner->isFlowDead() ) {
            return $result;
         } */
         if ( $result->returning ) {
            return $result;
         }
      }
      return $result;
   }

   private function visitStmt( \Node $stmt ): Result {
      if ( $stmt instanceof \ExprStmt ) {
         return $this->visitExprStmt( $stmt );
      }
      else {
         UNREACHABLE();
      }
   }

   private function visitExprStmt( \ExprStmt $stmt ): Result {
      $result = $this->visitExpr( $stmt->expr );
      if ( ! $stmt->yield && $result->reg !== null ) {
         $destroy = new DestroyInstruction();
         $destroy->reg = $result->reg;
         //$this->machine->appendInstruction( $destroy );
         $result->reg = null;
      }
      if ( ! $stmt->yield && $result->value !== null ) {
         $this->machine->drop( $result->value );
      }
      return $result;
   }

   private function visitExpr( \Expr $expr ): Result {
      return $this->visitExprRoot( $expr->root );
   }

   private function visitExprRoot( \Node $node ): Result {
      if ( $node instanceof \Let ) {
         return $this->visitLocalBinding( $node );
      }
      else if ( $node instanceof \IfStmt ) {
         return $this->visitIf( $node );
      }
      else if ( $node instanceof \WhileStmt ) {
         return $this->visitWhile( $node );
      }
      else if ( $node instanceof \Jump ) {
         return $this->visitJump( $node );
      }
      else if ( $node instanceof \ReturnStmt ) {
         return $this->visitReturn( $node );
      }
      else if ( $node instanceof \Assignment ) {
         return $this->visitAssignment( $node );
      }
      else if ( $node instanceof \Binary ) {
         return $this->visitBinary( $node );
      }
      else {
         return $this->visitPrefix( $node );
      }
   }

   private function visitLocalBinding( \Let $localBinding ): Result {
      $slot = new Slot();
      $result = $this->visitExpr( $localBinding->value );
      $binding = $this->scopeList->get( $localBinding->name );
      $result->value->name = $localBinding->name;
      $result->value->refCount++;
      $result->value->slot = $slot;
      $binding->value = $result->value;
      $binding->slot = $slot;
      $this->machine->movSlot( $binding->slot, $result->value );
      return $result;
   }

   private function visitIf( \IfStmt $stmt ): Result {
      return $this->visitUnfoldedIf( $stmt );
   }

   private function visitFoldedIf( \IfStmt $stmt ): Result {
      #$exit = $runner->createBlock();
      if ( $stmt->cond->value != 0 ) {
         return $this->visitBlockStmt( $stmt->body );
      }
      else {
         if ( $stmt->elseBody !== null ) {
            return $this->visitBlockStmt( $stmt->elseBody );
         }
      }
      #$runner->jumpToBlock( $body, $exit );
      #$runner->changeBlock( $exit );
      $result = new Result();
      return $result;
   }

   private function visitUnFoldedIf( \IfStmt $stmt ): Result {
      if ( ! $this->isVoid( $stmt->type ) ) {
         $this->machine->evictReturnValue();
      }

      $bodySections = [];
      $returnValue = null;
      $prevCondSection = null;
      $condSection = $prevCondSection;
      foreach ( $stmt->ifs as $i => $item ) {
         $condSection = $this->machine->separate();

         if ( $prevCondSection !== null ) {
            $this->machine->enter( $prevCondSection );
            $this->machine->jmpz( $condSection );
            $this->machine->enter( $condSection );
         }

         // Evaluate condition.
         $cond = $this->visitExpr( $item->cond );
         $this->machine->test( $cond->value );
         $this->machine->drop( $cond->value );

         // Evaluate body of if or elif expression.
         $bodySection = $this->machine->separate();
         array_push( $bodySections, $bodySection );
         $result = $this->visitBlockStmt( $item->body );

         // Put the return value of the expression in the same register as
         // the return value for a function.
         if ( ! $this->isVoid( $stmt->type ) ) {
            $this->machine->getReturnValue( $result->value );
            // HACK. Keep the value of the last if item so the machine knows
            // not to re-use the return value register.
            if ( $i === count( $stmt->ifs ) - 1 ) {
               $returnValue = $result->value;
            }
            else {
               $this->machine->drop( $result->value );
            }
         }

         $prevCondSection = $condSection;
      }

      $exitSection = $this->machine->separate();
      $elseSection = $exitSection;
      if ( $stmt->elseBody !== null ) {
         if ( $returnValue !== null ) {
            $this->machine->drop( $returnValue );
         }
         $result = $this->visitBlockStmt( $stmt->elseBody );
         if ( $returnValue !== null ) {
            $this->machine->getReturnValue( $result->value );
            $returnValue = $result->value;
         }
         $exitSection = $this->machine->separate();
      }

      $this->machine->enter( $condSection );
      $this->machine->jmpz( $elseSection );

      foreach ( $bodySections as $section ) {
         $this->machine->enter( $section );
         $this->machine->jmp( $exitSection );
      }

      $this->machine->enter( $exitSection );

      $result = new Result();
      $result->value = $returnValue;
      return $result;
   }

   private function visitWhile( \WhileStmt $stmt ): Result {
      $jumpSection = $this->machine->separate();
      $bodySection = $this->machine->separate();
      $condSection = $this->machine->separate();
      $exitSection = $this->machine->separate();

      $this->condSections[] = $condSection;
      $this->exitSections[] = $exitSection;

      $this->machine->enter( $jumpSection );
      //$this->machine->reserveSpace();
      //$this->machine->flushRegisters();

      $this->machine->enter( $condSection );
      $cond = $this->visitExpr( $stmt->cond );
      $this->machine->test( $cond->value );
      //$this->machine->drop( $cond->value );
      $this->machine->jmpnz( $bodySection );

      $this->machine->enter( $bodySection );
      $this->visitBlockStmt( $stmt->body );
      //$this->machine->flushRegisters();

      $this->machine->enter( $jumpSection );
      $this->machine->jmp( $condSection );
      $this->machine->enter( $exitSection );

      array_pop( $this->condSections );
      array_pop( $this->exitSections );

      $result = new Result();
      return $result;
   }

   private function visitJump( \Jump $stmt ): Result {
      switch ( $stmt->type ) {
      case JUMP_BREAK:
         $this->machine->jmp( end( $this->exitSections ) );
         break;
      case JUMP_CONTINUE:
         $this->machine->jmp( end( $this->condSections ) );
         break;
      default:
         UNREACHABLE();
      }
      $result = new Result();
      return $result;
   }

   private function visitReturn( \ReturnStmt $stmt ): Result {
      $result = new Result();
      $result->returning = true;
      $instruction = new RetInstruction();
      if ( $stmt->value != null ) {
         $value = $this->visitExpr( $stmt->value );
         $value->slot->critical = true;
         $instruction->value = $value->slot;
         $result->slot = $value->slot;
      }
      $runner->appendInstruction( $instruction );

      /*

      $result = new Result();
      $result->returning = true;
      $jump = new ReturnJump();
      if ( $stmt->value != null ) {
         $value = $this->visitExpr( $runner, $stmt->value );
         $value->slot->critical = true;
         $jump->value = $value->slot;
         $result->slot = $value->slot;
      }
      $runner->setBlockJump( $jump );
      return $result;
       */
      $result->flow = FLOW_DEAD;
      return $result;
   }

   private function visitAssignment( \Assignment $assignment ): Result {
      $lside = $this->visitExprRoot( $assignment->lside );
      $rside = $this->visitExprRoot( $assignment->rside );
      $binding = $this->scopeList->get( $lside->value->name );
      //$reservedSpace = $binding->value->reservedSpace;
      //$this->machine->drop( $binding->value );
      $binding->value = $rside->value;
      $binding->value->slot = $binding->slot;
      $binding->value->name = $lside->value->name;
      $binding->value->refCount++;

      $this->machine->movSlot( $binding->slot, $rside->value );

      //$binding->value->reservedSpace = $reservedSpace;
      //$this->machine->drop( $lside->value );
      return $rside;
   }

   private function visitBinary( Binary $binary ): Result {
      switch ( $this->describe( $binary->type ) ) {
      case Desc::INT:
         return $this->visitBinaryInt( $binary );
      case Desc::BOOL:
         return $this->visitBinaryBool( $binary );
      default:
         UNREACHABLE();
      }
   }

   private function visitBinaryInt( Binary $binary ): Result {
      $lside = $this->visitExprRoot( $binary->lside );
      $rside = $this->visitExprRoot( $binary->rside );
      switch ( $binary->op ) {
      case Binary::OP_ADD:
         $value = $this->machine->add( $lside->value, $rside->value );
         break;
      case Binary::OP_DIV:
         $value = $this->machine->idiv( $lside->value, $rside->value );
         break;
      default:
         UNREACHABLE();
      }
     // if ( $lside->slot->folded && $rside->slot->folded ) {
     //    return $this->FoldBinaryInt( $runner, $binary, $lside, $rside );
      //}

      $result = new Result();
      $result->value = $value;
      return $result;
   }

   private function foldBinaryInt( Binary $binary, Result $lside,
      Result $rside ): Result {
      switch ( $binary->op ) {
      case Binary::OP_ADD:
         $lside->slot->value += $rside->slot->value;
         break;
      default:
         UNREACHABLE();
      }
      $result = new Result();
      $result->slot = $lside->slot;
      $result->slot->folded = true;
      return $result;
   }

   private function visitBinaryBool( Binary $binary ): Result {
      $lside = $this->visitExprRoot( $binary->lside );
      $rside = $this->visitExprRoot( $binary->rside );
      switch ( $binary->op ) {
      case Binary::OP_EQ:
         $this->machine->cmp( $lside->value, $rside->value );
         $value = $this->machine->setz();
         break;
      case Binary::OP_NEQ:
         $this->machine->cmp( $lside->value, $rside->value );
         $value = $this->machine->setnz();
         break;
      case Binary::OP_LT:
         $this->machine->cmp( $lside->value, $rside->value );
         $value = $this->machine->setl();
         break;
      default:
         UNREACHABLE();
      }

      $result = new Result();
      $result->value = $value;
      return $result;
   }

   private function visitPrefix( \Node $node ): Result {
      switch ( $node->nodeType ) {
      case \NODE_UNARY:
         return $this->visitUnary( $node );
      case \NODE_LOGICAL_NOT:
         return $this->visitLogicalNot( $node );
      default:
      }
      return $this->visitSuffix( $node );
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

   private function visitBundleCall( \Call $call ): Result {

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
      switch ( $call->func->builtin ) {
      case \Func::BUILTIN_PRINTLN:
         return $this->callPrintln( $call );
      default:
         break;
      }

      // Inline single-use functions.
      //if ( $call->func->numCalls == 1 && $call->func->body !== null ) {
      //   return $this->inlineFunc( $runner, $call );
      //}

      $args = [];
      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $arg );
         $args[] = $result->value;
      }

      $operand = $this->visitSuffix( $call->operand );
      $funcName = $call->func->name;
      if ( $operand->method ) {
         $funcName = $operand->bundle->name . 'x' . $call->func->name;
      }
      $func = $this->machine->findFunc( $call->func );

      $returnValue = $this->machine->call( $func, $args );

      $result = new Result();
      $result->value = $returnValue;
      return $result;

      $instruction->func = $func;
      $instruction = new CallInstruction();

      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $arg );
         array_push( $instruction->args, $result->reg );
      }

      switch ( $call->func->builtin ) {
      case \Func::BUILTIN_PRINTLN:
         $instruction->func = $this->assembly->printf;
         break;
      default:
         $operand = $this->visitSuffix( $call->operand );
         $funcName = $call->func->name;
         if ( $operand->method ) {
            $funcName = $operand->bundle->name . 'x' . $call->func->name;
         }

         $instruction->func = $runner->findFunc( $call->func );

         #$instruction->userFunc = $this->script->funcsToPhpfuncs[ $funcName ];
      }

/*
      $args = [];
      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $runner, $arg );
         array_push( $args, $result );
      }
      $instruction->args = $args;
*/

      $runner->appendInstruction( $instruction );

      $result = new Result();

      if ( $call->func->returnType !== null ) {
         $returnValue = new VirtualRegister();
         $instruction->returnValue = $returnValue;
         $result->reg = $returnValue;
      }

      if ( $call->func->returnType &&
         $call->func->returnType->spec == TYPESPEC_STRUCT &&
         ! $this->isPrimitiveBundle( $call->func->returnType->bundle ) ) {
         $alloc = new CAlloc();
         $alloc->skipMalloc = true;
         $alloc->externalRefs = true;
         // We assume the returned allocation is referenced somewhere else
         // besides this function call.
         //$alloc->refCount = 1;
         $alloc->struct = $this->task->bundlesToStructs[
         $call->func->returnType->bundle->name ];
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

   private function callPrintln( \Call $call ): Result {
      $format = '';
      $args = [];
      foreach ( $call->args as $arg ) {
         $result = $this->visitExpr( $arg );
         switch ( $this->describe( $arg->type ) ) {
         case Desc::INT:
            $format .= '%d';
            array_push( $args, $result->slot );
            break;
         case Desc::BOOL:
            $t = $this->addString( 'true' );
            $f = $this->addString( 'false' );
            $slot = $this->passBoolArg( $result );
            array_push( $args, $slot );
            $format .= '%s';
            break;
         case Desc::STR:
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
      $instruction->func = $this->assembly->printf;
      $instruction->args = $args;
      $runner->appendInstruction( $instruction );
      $result = new Result();
      return $result;
   }

   private function inlineFunc( \Call $call ): Result {
      $value = $this->visitBlockStmt( $call->func->body );
      $result = new Result();
      $result->slot = $value->slot;
      return $result;
   }

   private function passBoolArg( Result $result ): Slot {
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
      case \NODE_BOOL_LITERAL:
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
         return new Result();
         break;
      default:
         UNREACHABLE();
      }
   }

   private function visitLocalBindingUsage( \NameUsage $usage ): Result {
      $binding = $this->scopeList->get( $usage->name );
      $result = new Result();
      $result->value = $binding->value;
      ++$binding->value->refCount;
      return $result;
   }

   private function visitIntegerLiteral( \IntegerLiteral $literal ): Result {
      $value = $this->machine->movImm( $literal->value );
      $result = new Result();
      $result->value = $value;
      return $result;
   }

   private function visitBoolLiteral( \BoolLiteral $literal ): Result {
      $value = $this->machine->movImm( $literal->value ? 1 : 0 );
      $result = new Result();
      $result->value = $value;
      return $result;
   }

   private function visitStringLiteral( \StringLiteral $literal ): Result {
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
      foreach ( $this->assembly->strings as $string ) {
         if ( $string->value == $value ) {
            return $string;
         }
      }
      $string = new IndexedString();
      $string->value = $value;
      $string->index = count( $this->assembly->strings );
      array_push( $this->assembly->strings, $string );
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

   private function visitParen( \Tuple $paren ): Result {
      return $this->visitExpr( $paren->expr );
   }
}
