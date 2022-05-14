<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

use Node;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description;
use Typing\InstanceChecker;
use Typing\InstanceCheckerUsage;
use Typing\Presenter;
use Typing\PresenterUsage;
use Typing\Type;

class CallWalker {
   use DescriberUsage;
   use InstanceCheckerUsage;
   use PresenterUsage;

   public function __construct(
      private Describer $typeDescriber,
      private InstanceChecker $instanceChecker,
      private Presenter $presenter,
      private ModuleWalk $moduleWalk,
      private ExprWalker $exprWalker,
      private SimpleExprWalker $basicExprWalker,
      private StackFrame $stackFrame,
      private CodegenTask $task,
      private CCompoundStmt $compoundStmt,
      private CTranslationUnit $unit,
   ) {}

   public function visitCall( \Call $call ): Result {
      switch ( $call->type ) {
      case \CALL_ENUM:
         return $this->visitEnumCall( $call );
      case \CALL_STRUCTURE:
         return $this->visitStructCall( $call );
      case \CALL_STRUCTURE_VALUE:
         return $this->visitStructValueCall( $call );
      case \CALL_FUNC:
         return $this->visitFuncCall( $call );
      case \CALL_TRAIT:
         return $this->visitTraitCall( $call );
      default:
         throw new \Exception();
      }
   }

   private function visitEnumCall( \Call $call ): Result {
      #$operand = $this->visitSuffix( $call->operand );

      // Tag.
      /*
      $literal = new CIntegerLiteral();
      $literal->value = $operand->enumerator->value;
      $expr = new CExpr();
      $expr->root = $literal;
      $initz = new CAllocInitializer();
      $initz->member = 'tag';
      $initz->value = $expr;
      $operand->alloc->initializers[] = $initz;
      */

      $this->visitSuffix( $call->operand );

      $arg = $this->visitExpr( $call->args[ 0 ]->expr );
      $var = $this->wrapValue( $arg->var, $call->enumerator->enumeration,
         $call->args[ 0 ]->expr->type );

      $result = new Result();
      $result->var = $var;
      return $result;

/*
      $count = 0;
      foreach ( $call->enumerator->params as $param ) {
         $initz = new CAllocInitializer();
         $initz->member = sprintf( 'u.m%d.%s', $operand->enumerator->index,
            $param->name );
         if ( $param->isRefType ) {
            $initz->incRefCount = true;
         }

         $operand->alloc->initializers[] = $initz;
         if ( $count < count( $call->args ) ) {
            $initz->value = $this->visitExpr( $call->args[ $count ] );
            if ( $param->isRefType ) {
               $initz->value->alloc->externalRefs = true;
            }
         }
         else {
            $initz->value = $this->visitExpr( $param->defaultArg );
         }
         ++$count;
      }
      //var_dump( $operand->alloc ); exit( 1 );
      return $operand;
      */
   }

   private function visitStructCall( \Call $call ): Result {
      switch ( $call->structure->builtin ) {
      case BUILTIN_STRUCTURE_INT:
         return $this->visitIntCall( $call );
      }

      $this->visitSuffix( $call->operand );

      $alloc = new CAlloc();
      $alloc->struct = $this->moduleWalk->getCStruct( $call->structure );

      $count = 0;
      foreach ( $call->structure->members as $member ) {
         $initz = new CAllocInitializer();
         //$initz->member = $member->name;
         $initz->memberInt = $count;
         if ( $this->isRefMember( $member ) ) {
            $initz->incRefCount = true;
         }

         $alloc->initializers[] = $initz;
         if ( $count < count( $call->args ) ) {
            $result = $this->visitExpr( $call->args[ $count ]->expr );
            $expr = new CExpr();
            $expr->result = $result->var;
            //$expr->type = $result->;
            //$initz->value = $expr;
            $initz->value = $result->var;

            //if ( $initz->value->alloc ) {
            //   $initz->value->alloc->externalRefs = true;
            //}
            //$this->cExpr->allocs = array_merge( $this->cExpr->allocs,
            //   $alloc->initializers );
            //$expr->allocs = [];
         }
         else {
            $result = $this->visitExpr( $member->defaultInitializer );
            $initz->value = $result->var;
         }
         ++$count;
         /*
            if ( $count < count( $literal->initializers ) ) {
               $expr = $this->visitExpr( $literal->initializers[ $count ] );
               array_push( $alloc->initializers, $expr );
            }
            else {
               $expr = $this->visitExpr( $member->defaultInitializer );
               array_push( $alloc->initializers, $expr );
            }
            ++$count;*/
      }

      $result = new Result();
      $this->append( $alloc );

      $var = $this->stackFrame->allocVar();
      $var->type->struct = $alloc->struct;
      $var->type->spec = SPEC_STRUCTPTR;
      $result->var = $var;
      $alloc->var = $var;

      return $result;
   }

   private function isRefMember( \StructureMember $member ): bool {
      return ( $member->type->spec == TYPESPEC_STRUCT &&
         ! $this->isPrimitiveStruct( $member->type ) );
   }

   private function isPrimitiveStruct( Type $type ): bool {
      switch ( $this->describe( $type ) ) {
      case Description::INT:
      case Description::BOOL:
         return true;
      default:
         return false;
      }
   }

   private function visitIntCall( \Call $call ): Result {
      return $this->visitExpr( $call->args[ 0 ]->expr );
   }

   private function visitStructValueCall( \Call $call ): Result {
      $operand = $this->visitSuffix( $call->operand );
      $index = $this->visitExpr( $call->args[ 0 ]->expr );
      $struct = $this->moduleWalk->getCStruct( $call->structure );

      $var = $this->stackFrame->allocVar();
      $var->type = $struct->members[ 0 ]->type;

      $subscript = new CSubscript();
      $subscript->base = $operand->var;
      $subscript->index = $index->var;
      $subscript->result = $var;
      $this->append( $subscript );

      $result = new Result();
      $result->var = $var;

      return $result;
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
      $operand = $this->visitSuffix( $call->operand );

      if ( $operand->method && $call->func->builtin !== \Func::BUILTIN_NONE ) {
         return $this->visitBuiltinMethodCall( $call, $operand );
      }

      $funcName = $call->func->name;
      if ( $funcName === '' ) {
         $funcName = $operand->cFunc->name;
      }
      if ( $operand->method && $call->func->builtin === \Func::BUILTIN_NONE ) {
         $funcName = $operand->structure->name . 'xx' . $funcName;
      }

      $cCall = new CCall();
      $cCall->func = CCALL_USER;
      $cCall->userFunc = $this->task->funcsToCfuncs[ $funcName ];

      // Arguments.
      $count = 0;
      $argPos = 0;

      // Self argument.
      if ( $operand->method || $operand->trait ) {
         $arg = new CArg();
         $arg->var = $operand->var;
         $cCall->args[] = $arg;
         ++$count;
      }

      // Positional arguments.
      while ( true ) {
         if ( ! ( $count < count( $call->func->params ) &&
            $call->func->params[ $count ]->defaultArg === null ) ) {
            break;
         }

         $expr = $this->visitExpr( $call->args[ $argPos ]->expr );
         $param = $call->func->params[ $count ];

         $arg = new CArg();
         $arg->var = $expr->var;

         if ( $param->type->borrowed ) {
            $arg->addrof = true;
         }

         if ( $param->type->spec == TYPESPEC_TRAIT ) {
         }
         else if ( $param->type->spec == TYPESPEC_STRUCT &&
            ! $this->isPrimitiveStruct( $param->type ) ) {
            //$expr->alloc->externalRefs = true;
            //$cCall->args[] = $expr->var;
         }
         else if ( $this->describe( $param->type ) === Description::ENUM ) {
            $arg->var = $this->wrapValue( $arg->var,
               $param->type->enumeration,
               $call->args[ $argPos ]->expr->type );
         }
         else {
            //$cCall->args[] = $expr->var;
         }

         $cCall->args[] = $arg;

         ++$count;
         ++$argPos;
      }


      // Optional arguments.
      while ( true ) {
         if ( ! ( $count < count( $call->func->params ) &&
            $call->func->params[ $count ]->defaultArg !== null ) ) {
            break;
         }

         $param = $call->func->params[ $count ];
         $arg = $param->defaultArg;
         if ( $argPos < count( $call->args ) ) {
            if ( $call->args[ $argPos ]->name !== '' ) {
               foreach ( $call->args as $passedArg ) {
                  if ( $passedArg->name === $param->name ) {
                     $arg = $passedArg->expr;
                     break;
                  }
               }
            }
            else {
               $arg = $call->args[ $argPos ]->expr;
               ++$argPos;
            }
         }

         $expr = $this->visitExpr( $arg );
         $cCall->args[] = $expr->var;

         ++$count;
      }

      // Remaining arguments.
      while ( $count < count( $call->args ) ) {
         $expr = $this->visitExpr( $call->args[ $count ]->expr );
         $arg = new CArg();
         $arg->var = $expr->var;
         $cCall->args[] = $arg;
         ++$count;
      }

      $result = new Result;

      if ( ! $this->isVoid( $call->func->returnType ) ) {
         $cCall->returnValue = $this->stackFrame->allocVar();
         $cCall->returnValue->type = $this->moduleWalk->createCType(
            $call->func->returnType );
         $result->var = $cCall->returnValue;
      }

      $this->append( $cCall );

      return $result;
   }

   private function wrapValue( CVar $value,
      \Enumeration $enumeration, Type $type ): CVar {
      $enumerator = $this->instanceChecker->findEnumerator( $enumeration,
         $type );

      $struct = $this->unit->structs->get( $enumeration->index );
      $unionValue = new CUnionValue();
      $unionValue->struct = $struct;
      $unionValue->member = $enumerator->index;
      $unionValue->value = $value;
      $unionValue->result = $this->stackFrame->allocVar();
      $unionValue->result->type->spec = SPEC_STRUCTPTR;
      $unionValue->result->type->struct = $struct;
      $this->append( $unionValue );
      return $unionValue->result;
   }

   private function visitBuiltinMethodCall( \Call $call,
      Result $operand ): Result {
      switch ( $call->func->builtin ) {
      case \Func::BUILTIN_STR_PTR:
      case \Func::BUILTIN_INT_UNWRAP:
         return $operand;
      default:
         throw new \Exception();
      }
   }

   private function visitVirtFuncCall( \Call $call ): Result {
      $cLiteral = new CIntegerLiteral();
      $cLiteral->value = $call->result;
      $result = new Result();
      return $result;
   }

   private function visitTraitCall( \Call $call ): Result {
      $operand = $this->visitSuffix( $call->operand );

/*
      $cAccess = new CAccess();
      $cAccess->object = $operand->node;
      $cAccess->member = sprintf( 'interface->%s', $call->func->name );

      $cCall = new CCall();
      $cCall->func = CCALL_OPERAND;
      $cCall->operand = $cAccess;

      $cAccess = new CAccess();
      $cAccess->object = $operand->node;
      $cAccess->member = sprintf( 'object' );

      $expr = new CExpr();
      $expr->root = $cAccess;
      array_push( $cCall->args, $expr );
*/
      $result = new Result;
  //    $result->node = $cCall;
      return $result;
   }

   private function visitSuffix( Node $operand ): Result {
      return $this->basicExprWalker->visitSuffix( $operand );
   }

   private function visitExpr( \Expr $expr ): Result {
      return $this->exprWalker->visitExpr( $expr );
   }

   private function append( CNode $operation ): void {
      $this->exprWalker->append( $operation );
   }
}
