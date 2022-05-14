<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

use Let;
use \Node;
use \Func;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description;
use Typing\InstanceChecker;
use Typing\InstanceCheckerUsage;
use Typing\Presenter;
use Typing\Type;
use const Typing\NATIVE_TYPE_I64;

class SimpleExprWalker {
   use DescriberUsage;
   use InstanceCheckerUsage;

   private CodegenTask $task;
   private CTranslationUnit $unit;
   private ?cExpr $cExpr;
   private ScopeList $scopeList;

   public function __construct(
      private Describer $typeDescriber,
      private InstanceChecker $instanceChecker,
      private Presenter $presenter,
      private ModuleWalk $moduleWalk,
      private ExprWalker $exprWalker,
      private StackFrame $stackFrame,
      private CCompoundStmt $compoundStmt,
      CodegenTask $task,
      CTranslationUnit $unit,
      ScopeList $scopeList ) {
      $this->task = $task;
      $this->unit = $unit;
      $this->cExpr = null;
      $this->scopeList = $scopeList;
   }

   public function visitSimpleExpr( Node $node ): Result {
      if ( $node instanceof Let ) {
         return $this->visitLet( $node );
      }
      else if ( $node instanceof \Assignment ) {
         return $this->visitAssignment( $node );
      }
      else if ( $node instanceof \Binary ) {
         return $this->visitBinary( $node );
      }
      else if ( $node instanceof \Logical ) {
         return $this->visitLogical( $node );
      }
      else {
         return $this->visitPrefix( $node );
      }
   }

   private function visitAssignment( \Assignment $assignment ): Result {
      $lside = $this->visitSuffix( $assignment->lside );
      $rside = $this->visitSimpleExpr( $assignment->rside );
      if ( $lside->member !== null ) {
         $cAssignment = new CAssignment();
         $cAssignment->rside = $rside->var;

         switch ( $lside->member->builtin ) {
         case \BuiltinStructMember::VALUE:
            $cAssignment->lside = $lside->var;

            if ( $lside->var->type->pointers !== [] ) {
               $cAssignment->deref = true;
            }

            if ( $rside->var->type->pointers !== [] ) {
               $deref = new CPointerDeref();
               $deref->operand = $rside->var;
               $deref->result = $this->stackFrame->allocVar();
               $deref->result->type = $rside->var->type->deref();
               $this->append( $deref );
               $cAssignment->rside = $deref->result;
            }

            break;
         default:
            throw new \Exception();
         }
         $this->append( $cAssignment );
      }
      else {
         $this->associate( $lside->name, $rside->var );
      }
      return $rside;
   }

   private function associate( string $label, CVar $var ): void {
      $binding = $this->scopeList->create( $label );
      if ( $binding->var !== null ) {
         $this->stackFrame->release( $binding->var );
      }
      $binding->var = $var;
      $var->label = $label;
      ++$var->refs;
   }

   private function visitLogical( \Logical $logical ): Result {
      $lside = $this->visitSimpleExpr( $logical->lside );
      $rside = $this->visitSimpleExpr( $logical->rside );


      $value = $this->stackFrame->allocVar();
      $value->type->spec = SPEC_BOOL;

      $cBinary = new CBinary();
      $cBinary->result = $value;
      $cBinary->lside = $lside->var;
      $cBinary->rside = $rside->var;
      switch ( $logical->operator ) {
      case \Logical::OPERATOR_AND:
         $cBinary->op = CBINARY_LOGAND;
         break;
      case \Logical::OPERATOR_OR:
         $cBinary->op = CBINARY_LOGOR;
         break;
      }

      $result = new Result;
      $this->append( $cBinary );

      return $result;
   }

   private function visitBinary( \Binary $binary ): Result {
      switch ( $binary->implementer ) {
      case \Implementer::PTR:
         return $this->visitBinaryPtr( $binary );
      }
      $lside = $this->visitSimpleExpr( $binary->lside );
      $rside = $this->visitSimpleExpr( $binary->rside );
      $cBinary = new CBinary();

      $value = $this->stackFrame->allocVar();
      if ( count( $rside->var->type->pointers ) > 0 ) {
         $value->type = $rside->var->type;
      }
      else {
         $value->type->spec = SPEC_INT64;
      }

      $cBinary->result = $value;
      $cBinary->lside = $lside->var;
      $cBinary->rside = $rside->var;
      $this->stackFrame->release( $lside->var );
      $this->stackFrame->release( $rside->var );

      $result = new Result;
      $result->var = $cBinary->result;
      $this->append( $cBinary );
      switch ( $binary->op ) {
      case \Binary::OP_EQ:
         $cBinary->op = CBINARY_EQ;
         $value->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_NEQ:
         $cBinary->op = CBINARY_NEQ;
         $value->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_LT:
         $cBinary->op = CBINARY_LT;
         $value->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_LTE:
         $cBinary->op = CBINARY_LTE;
         $value->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_GT:
         $cBinary->op = CBINARY_GT;
         $value->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_GTE:
         $cBinary->op = CBINARY_GTE;
         $value->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_ADD:
         $cBinary->op = CBINARY_ADD;
         break;
      case \Binary::OP_SUB:
         $cBinary->op = CBINARY_SUB;
         break;
      case \Binary::OP_MUL:
         $cBinary->op = CBINARY_MUL;
         break;
      case \Binary::OP_DIV:
         $cBinary->op = CBINARY_DIV;
         break;
      case \Binary::OP_MOD:
         $cBinary->op = CBINARY_MOD;
         break;
      default:
         throw new \Exception();
      }

      return $result;
   }

   private function visitBinaryPtr( \Binary $binary ): Result {
      $lside = $this->visitSimpleExpr( $binary->lside );
      $rside = $this->visitSimpleExpr( $binary->rside );

      $cBinary = new CBinary();
      $cBinary->result = $this->stackFrame->allocVar();
      $cBinary->lside = $lside->var;
      $cBinary->rside = $rside->var;
      $this->stackFrame->release( $lside->var );
      $this->stackFrame->release( $rside->var );

      switch ( $binary->op ) {
      case \Binary::OP_EQ:
         $cBinary->op = CBINARY_EQ;
         $cBinary->result->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_NEQ:
         $cBinary->op = CBINARY_NEQ;
         $cBinary->result->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_LT:
         $cBinary->op = CBINARY_LT;
         $cBinary->result->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_LTE:
         $cBinary->op = CBINARY_LTE;
         $cBinary->result->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_GT:
         $cBinary->op = CBINARY_GT;
         $cBinary->result->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_GTE:
         $cBinary->op = CBINARY_GTE;
         $cBinary->result->type->spec = SPEC_BOOL;
         break;
      case \Binary::OP_ADD:
         $cBinary->op = CBINARY_ADD;
         $cBinary->result->type = $lside->var->type;
         break;
      case \Binary::OP_SUB:
         $cBinary->op = CBINARY_SUB;
         if ( count( $rside->var->type->pointers ) > 0 ) {
            $cBinary->result->type->spec = SPEC_INT64;
         }
         else {
            $cBinary->result->type = $lside->var->type;
         }
         break;
      default:
         throw new \Exception();
      }

      $result = new Result;
      $result->var = $cBinary->result;
      $this->append( $cBinary );

      return $result;
   }

   private function visitPrefix( Node $node ): Result {
      if ( $node instanceof \Unary ) {
         return $this->visitUnary( $node );
      }
      else if ( $node instanceof \LogicalNot ) {
         return $this->visitLogicalNot( $node );
      }
      else {
         return $this->visitPossibleLike( $node );
      }
   }

   private function visitUnary( \Unary $unary ): Result {
      $operand = $this->visitPrefix( $unary->operand );
      if ( $unary->op === UOP_PRE_INC || $unary->op === UOP_PRE_DEC ) {
         return $this->visitIncrement( $unary, $operand );
      }
      else if ( $unary->op == UOP_PLUS ) {
         return $operand;
      }
      else if ( $unary->op === UOP_ADDR_OF ) {
         switch ( $operand->var->type->spec ) {
         case SPEC_STRUCTPTR:
            $share = new CShare();
            $share->var = $operand->var;
            $this->append( $share );
            return $operand;
         default:
            return $operand;
         }
      }
      else {
         $cUnary = new CUnary();
         $cUnary->operand = $operand->var;
         switch ( $unary->op ) {
         case UOP_MINUS:
            $cUnary->op = CUOP_MINUS;
            break;
         default:
            throw new \Exception();
         }
         $result = new Result;
         $result->var = $this->stackFrame->allocVar();
         $result->var->type = $operand->var->type;
         $cUnary->result = $result->var;
         $this->append( $cUnary );
         return $result;
      }
   }

   private function visitIncrement( \Unary $unary, Result $operand ): Result {
      if ( count( $operand->var->type->pointers ) > 0 ) {
         $operation = new CUnary();
         $operation->op = CUOP_PRE_INC;
         $operation->operand = $operand->var;
         $this->append( $operation );
         return $operand;
      }
   }

   private function visitLogicalNot( \LogicalNot $logicalNot ): Result {
      $operand = $this->visitPrefix( $logicalNot->operand );
      $cUnary = new CUnary();
      $cUnary->operand = $operand->var;
      $cUnary->op = CUOP_NOT;
      $result = new Result;
      $this->append( $cUnary );
      return $result;
   }

   private function visitPossibleLike( \Node $node ): Result {
      if ( $node instanceof \Like ) {
         return $this->visitLike( $node );
      }
      else {
         return $this->visitSuffix( $node );
      }
   }

   private function visitLike( \Like $like ): Result {
      $operand = $this->visitSuffix( $like->operand );

      switch ( $like->pattern->type ) {
      case PATTERN_NAME:
         $result = $this->visitNamePattern( $operand, $like->pattern );
         break;
      default:
         throw new \Exception();
      }





      //$result = new Value();
      //$result->type = $this->typeChecker->createBoolType();
      //$result->evaluable = $operand->evaluable;
      return $result;
   }

   private function visitNamePattern( Result $operand,
      \Pattern $pattern ): Result {
      $match = new CTagMatch();
      $match->operand = $operand->var;
      $match->member = $pattern->enumerator->index;
      $match->result = $this->stackFrame->allocVar();
      $match->result->type->spec = SPEC_BOOL;
      $this->append( $match );
      $result = new Result();
      $result->var = $match->result;
      return $result;
   }

   public function visitSuffix( Node $node ): Result {
      if ( $node instanceof \Access ) {
         return $this->visitAccess( $node );
      }
      else if ( $node instanceof \Subscript ) {
         return $this->visitSubscript( $node );
      }
      else if ( $node instanceof \Call ) {
         return $this->visitCall( $node );
      }
      else {
         return $this->visitPrimary( $node );
      }
   }

   private function visitAccess( \Access $access ): Result {
      switch ( $access->type ) {
      case ACCESS_ERR:
         return $this->accessErr( $access );
      case ACCESS_MEMBER:
         return $this->accessMember( $access );
      case ACCESS_TRAIT_MEMBER:
         return $this->accessTraitMember( $access );
      case ACCESS_METHOD:
         return $this->accessMethod( $access );
      }
   }

   private function accessErr( \Access $access ): Result {
      $err = new CErr();
      $err->message = $access->err->message;
      $result = new Result();
      //$result->node = $err;
      return $result;
   }

   private function accessMember( \Access $access ): Result {
      $lside = $this->visitSuffix( $access->lside );

      $struct = $this->moduleWalk->getCStruct( $access->structure );

      $index = 0;
      foreach ( $access->structure->members as $member ) {
         if ( $member->name == $access->memberName ) {
            break;
         }
         ++$index;
      }

      if ( $member->builtin === \BuiltinStructMember::VALUE ) {
         $result = new Result();
         $result->member = $access->structure->members[ $index ];
         $result->var = $lside->var;
         return $result;
      }

      $deref = new CDeref();
      $deref->operand = $lside->var;
      $deref->member = $index;
      $deref->isBool = $access->isBool;
      $deref->subscript = ( $access->structure->homogeneous === true );

      $var = $this->stackFrame->allocVar();
      if ( $struct->homogeneous ) {
         $var->type = $struct->members[ 0 ]->type;
      }
      else {
         $var->type = $struct->members[ $index ]->type;
      }
      $deref->result = $var;
      $this->append( $deref );

      $result = new Result();
      $result->member = $access->structure->members[ $index ];
      $result->var = $var;


      return $result;
   }

   private function accessTraitMember( \Access $access ): Result {
      $lside = $this->visitSuffix( $access->lside );

/*
      $cAccess = new CAccess();
      $cAccess->object = $lside->node;
      $cAccess->member = sprintf( 'interface->%s', $access->memberName );
      $lside->node = $cAccess;*/

      $lside->method = true;
      return $lside;
   }

   private function accessMethod( \Access $access ): Result {
      $lside = $this->visitSuffix( $access->lside );
      $lside->structure = $access->structure;
      $lside->method = true;
      return $lside;
   }

   private function visitSubscript( \Subscript $subscript ): Result {
      if ( $subscript->isPointer ) {
         if ( $subscript->value !== null ) {
            $operand = $this->visitSuffix( $subscript->operand );
            $index = null;
            if ( count( $subscript->indexes ) >= 1 ) {
               $index = $this->exprWalker->visitExpr(
                  $subscript->indexes[ 0 ] )->var;
            }
            $value = $this->visitSuffix( $subscript->value );

            $deref = new CPointerDeref();
            $deref->operand = $operand->var;
            $deref->index = $index;
            $deref->result = $this->stackFrame->allocVar();
            $deref->result->type = $operand->var->type->deref();
            $deref->value = $value->var;

            $result = new Result();
            $result->var = $deref->result;
            $this->append( $deref );

            return $result;
         }
         else {
            $operand = $this->visitSuffix( $subscript->operand );
            $deref = new CPointerDeref();
            $deref->operand = $operand->var;
            if ( count( $subscript->indexes ) >= 1 ) {
               $index = $this->exprWalker->visitExpr( $subscript->indexes[ 0 ] );
               $deref->index = $index->var;
            }
            $this->append( $deref );


            $deref->result = $this->stackFrame->allocVar();
            $deref->result->type = $operand->var->type->deref();

            $result = new Result();
            $result->var = $deref->result;
            return $result;
         }
      }
      else {
         throw new \Exception();
      }
   }

   private function visitCall( \Call $call ): Result {
      $callWalker = new CallWalker( $this->typeDescriber,
         $this->instanceChecker,
         $this->presenter,
         $this->moduleWalk, $this->exprWalker,
         $this, $this->stackFrame, $this->task, $this->compoundStmt,
         $this->unit );
      return $callWalker->visitCall( $call );
   }

   private function visitPrimary( Node $node ): Result {
      if ( $node instanceof \NullPointer ) {
         return $this->visitNullPointer();
      }
      else if ( $node instanceof \Sizeof ) {
         return $this->visitSizeof( $node );
      }
      else if ( $node instanceof \NameUsage ) {
         return $this->visitNameUsage( $node );
      }
      else if ( $node instanceof \IntegerLiteral ) {
         return $this->visitIntegerLiteral( $node->value );
      }
      else if ( $node instanceof \BoolLiteral ) {
         return $this->visitBoolLiteral( $node );
      }
      else if ( $node instanceof \StringLiteral ) {
         return $this->visitStringLiteral( $node );
      }
      else if ( $node instanceof \Tuple ) {
         return $this->visitTuple( $node );
      }
      else if ( $node instanceof \DropExpr ) {
         return $this->visitDrop( $node );
      }
      else if ( $node instanceof \Structure ) {
         return $this->visitStruct( $node );
      }
      else if ( $node instanceof \Enumeration ) {
         return $this->visitEnum( $node );
      }
      else {
         printf( "unhandled node: %s\n", get_class( $node ) );
         throw new \Exception();
      }
   }

   private function visitLet( \Let $let ): Result {
   /*
      $args = [];
      foreach ( $let->unpackedTuple as $arg ) {
         $args[] = $this->visitExpr( $arg->value );
      }
      */
/*
      $type = $this->createCType( $param->type );
      $cParam = new CParam();
      $cParam->name = $param->name;
      $cParam->spec = $type->spec;
      $cParam->struct = $type->struct;
      $cParam->pointers = $type->pointers;
      $cParam->params = $type->params;
      $cParam->index = count( $cFunc->params ) + 1;
      $cParam->type = $type;
      $cFunc->params[] = $cParam;

      $var = new CVar();
      $var->name = $cParam->name;
      $var->type = $type;
      $cParam->var = $var;

      $param->cParam = $cParam;
      */

      if ( $let->value === null ) {
         $count = 0;
         foreach ( $let->unpackedTuple as $element ) {
            if ( $element->defaultArg !== null ) {
               $value = $this->visitExpr( $element->defaultArg );

               if ( $this->describe( $element->type ) === Description::ENUM ) {
                  $this->moduleWalk->addEnumType( $element->type->enumeration );
               }

               $type = $this->moduleWalk->createCType( $element->type );
               $cParam = new CParam();
               $cParam->name = $element->name;
               $cParam->type = $type;
               $cParam->index = $count;

               $cParam->var = $value->var;

               $element->cParam = $cParam;
               ++$count;
            }
         }

         $result = new Result();
         $result->var = $value->var;

         if ( ! $this->isVoid( $element->type ) ) {
            $this->associate( $element->name, $result->var );
         }

         return $result;
      }


      $result = $this->exprWalker->visitExpr( $let->value );

      if ( ! $this->isVoid( $let->type ) ) {
         $this->associate( $let->name, $result->var );
      }

      //$binding->alloc = $result->alloc;
      //$result->binding = $binding;
     // $result->node = $result->alloc;

      //$result->binding2 = $this->scopeList->create( $let->name );


      //if ( isset( $result->binding->assignment ) ) {
      //   $result->node = $result->binding->assignment;
      //}

      return $result;
   }

   private function visitNullPointer(): Result {
      $result = new Result();
      //$result->node = new CNullPointer();
      return $result;
   }

   private function visitSizeof( \Sizeof $sizeof ): Result {
      $literal = new CIntegerLiteral();
      $literal->value = $sizeof->size;
      $result = new Result();
      //$result->node = $literal;
      return $result;
   }

   private function visitNameUsage( \NameUsage $usage ): Result {
   /*
      if ( $usage->binding ) {
         $result = new Result();
         $result->binding = $usage->binding;
         $result->alloc = $result->binding->alloc;
         if ( $result->alloc ) {
            $result->node = $result->alloc;
         }

         $result->binding2 = $this->scopeList->get( $usage->name );
         $result->alloc = $result->binding2->alloc;

         if ( $usage->binding->param != null ) {
            $result = $this->visitParam( $usage, $usage->binding->param );
         }

         //if ( isset( $result->binding->assignment ) ) {
         //   $result->node = $result->binding->assignment;
         //}

         return $result;
      } */


      if ( $usage->object instanceof Let ) {
         return $this->visitLetUsage( $usage->object );
      }
      else if ( $usage->object instanceof \Constant ) {
         return $this->visitConstant( $usage->object );
      }
      else if ( $usage->value->type->value instanceof \Enumerator ) {
         return $this->visitUnionValue( $usage );
      }
      else if ( $usage->object instanceof \Param ) {
         return $this->visitParam( $usage, $usage->object );
      }
      else if ( $usage->object instanceof Func ) {
         $result = new Result();
         $result->cFunc = $this->task->funcsToCfuncs[ $usage->object->name ];
         return $result;
      }
      else if ( $usage->object instanceof \Enumeration ) {
         return $this->visitEnum( $usage->object );
      }
      else if ( $usage->object instanceof \Structure ) {
         return $this->visitStruct( $usage->object );
      }
      else if ( $usage->object instanceof \Generic ) {
         return $this->visitGeneric( $usage->object );
      }
      else {
      var_dump( get_class( $usage->object ) );
         throw new \Exception();
      }
   }

   private function visitLetUsage( Let $let ): Result {
      $binding = $this->scopeList->get( $let->name );
      //if (
      //return $this->visitExprRoot( $let->value->root );
      $result = new Result();
      if ( $binding !== null ) {
         $result->var = $binding->var;
         $result->var->refs++;
      }
      else {
         if ( $let->value->constant ) {
            $var = $this->stackFrame->allocVar();
            $var->type = $this->moduleWalk->createCType( $let->value->type );
            $assignment = new CIntegerLiteralAssignment();
            $assignment->var = $var;
            $assignment->value = $let->value->value;
            $this->append( $assignment );
            $result->var = $var;
         }
      }
      $result->name = $let->name;
      return $result;
   }

   private function visitConstant( \Constant $constant ): Result {
      if ( $constant->value->value instanceof Func ) {
         $result = new Result();
         $result->cFunc = $this->task->funcsToCfuncs[ $constant->name ];
         return $result;
      }
      else {
         $var = $this->stackFrame->allocVar();
         $var->type->spec = SPEC_INT64;

         $assignment = new CIntegerLiteralAssignment();
         $assignment->var = $var;
         $assignment->value = $constant->value->value;
         $this->append( $assignment );

         /*
         $cLiteral = new CIntegerLiteral();
         $cLiteral->value = $literal->value;

               $cAssignment = new CAssignment();
               $cAssignment->value = $cLiteral;
               $cAssignment->type = RUNTIMEVALUE_INT;
               $cAssignment->var = $this->allocLocalVar();
         */
         $result = new Result();
         $result->var = $var;
         return $result;
      }
   }

   private function visitEnumerator( \Enumerator $enumerator ): Result {
      if ( count( $enumerator->params ) > 0 ) {
         $alloc = new CAlloc();
         $alloc->struct = $this->moduleWalk->getCStruct(
            $enumerator->structure );

         $result = new Result();
         $result->enumerator = $enumerator;
         return $result;
      }
      else {
         $cLiteral = new CIntegerLiteral();
         $cLiteral->value = $enumerator->value;

         /*
               $cAssignment = new CAssignment();
               $cAssignment->value = $cLiteral;
               $cAssignment->type = RUNTIMEVALUE_INT;
               $cAssignment->var = $this->allocLocalVar();
         */
         $result = new Result();
         //$result->node = $cLiteral;
         return $result;
      }
   }

   private function visitUnionValue( \NameUsage $usage ): Result {
      $binding = $this->scopeList->get( $usage->name );

      if ( $binding === null || $binding->var === null ) {
         throw new \Exception();
      }

      $access = new CUnionAccess();
      $access->operand = $binding->var;
      $access->member = $usage->value->type->value->index;
      $access->result = $this->stackFrame->allocVar();
      $access->result->type = $this->moduleWalk->createCType(
         $usage->value->type );
      $this->append( $access );

      $result = new Result();
      $result->name = $usage->name;
      $result->var = $access->result;

      return $result;
   }

   private function visitParam( \NameUsage $usage, \Param $param ): Result {
      $cUsage = new CNameUsage();
      $cUsage->object = $param->cParam;
      $cUsage->isParam = true;
      $result = new Result();
      $result->var = $param->cParam->var;
      $result->name = $param->name;
      if ( $param->type->spec == TYPESPEC_TRAIT ) {
         $result->trait = $param->type->trait;
      }

      if ( $usage->argsListSpecified ) {
         $deref = new CPointerDeref();
         $deref->operand = $param->cParam;
         if ( count( $usage->args ) >= 1 ) {
            $index = $this->exprWalker->visitExpr( $usage->args[ 0 ] );
            $deref->index = $index->var;
         }
         $this->append( $deref );
         $deref->result = $this->stackFrame->allocVar();
         $deref->result->type = $this->moduleWalk->createCType( $param->type );
         $deref->result->type = $deref->result->type->deref();
         $result->var = $deref->result;
      }
      else {

      }

      return $result;
   }

   private function isRefMember( \StructureMember $member ): bool {
      return ( $member->type->spec == TYPESPEC_STRUCT &&
         ! $this->isPrimitiveStruct( $member->type->structure ) );
   }

   private function isPrimitiveStruct( \Structure $structure ): bool {
      switch ( $structure->name ) {
      case 'Int':
      case 'Bool':
         return true;
      default:
         return false;
      }
   }

   private function visitIntegerLiteral( int $value ): Result {
      $var = $this->stackFrame->allocVar();
      $var->type->spec = SPEC_INT64;

      $assignment = new CIntegerLiteralAssignment();
      $assignment->var = $var;
      $assignment->value = $value;
      $this->append( $assignment );

      /*
      $cLiteral = new CIntegerLiteral();
      $cLiteral->value = $literal->value;

            $cAssignment = new CAssignment();
            $cAssignment->value = $cLiteral;
            $cAssignment->type = RUNTIMEVALUE_INT;
            $cAssignment->var = $this->allocLocalVar();
      */
      $result = new Result();
      $result->var = $var;
      return $result;
   }

   private function append( CNode $operation ): void {
      $this->exprWalker->append( $operation );
   }

   private function visitBoolLiteral( \BoolLiteral $literal ): Result {
      $var = $this->stackFrame->allocVar();
      $var->type->spec = SPEC_INT64;

      $assignment = new CIntegerLiteralAssignment();
      $assignment->var = $var;
      $assignment->value = $literal->value;
      $this->append( $assignment );

      $result = new Result();
      $result->var = $var;
      return $result;
   }

   private function visitStringLiteral( \StringLiteral $literal ): Result {
      $var = $this->stackFrame->allocVar();
      $var->type->spec = SPEC_STR;

      $assignment = new CStringLiteralAssignment();
      $assignment->var = $var;
      $assignment->value = $literal->value;
      $this->append( $assignment );

      $result = new Result();
      $result->var = $var;
      return $result;

      $cLiteral = new CIntegerLiteral();
      $cLiteral->value = $literal->index;
      $result = new Result();
      return $result;
   /*
      $nameUsage = new CNameUsage();
      $nameUsage->name = 'strtbl';
      $integerLiteral = new CIntegerLiteral();
      $integerLiteral->value = $literal->index;
      $index = new CExpr();
      $index->root = $integerLiteral;
      $deref = new CPointerDeref();
      $deref->operand = $nameUsage;
      $deref->index = $index;
      $result = new Result();
      $result->node = $deref;
      return $result; */
   /*
      $cLiteral = new CStringLiteral();
      $cLiteral->value = $literal->value;
      $result = new Result();
      $result->node = $cLiteral;
      return $result;*/
   }

   private function visitEnum( \Enumeration $enumeration ): Result {
      $this->moduleWalk->addEnumType( $enumeration );
      return new Result();
   }

   private function visitStruct( \Structure $structure ): Result {
      $this->moduleWalk->addStructType( $structure );
      return new Result();
   }

   private function visitTuple( \Tuple $tuple ): Result {
      if ( count( $tuple->args ) > 0 ) {
         return $this->visitSizedTuple( $tuple );
      }
      else {
         return new Result();
      }
   }

   private function visitSizedTuple( \Tuple $tuple ): Result {
      $values = [];
      foreach ( $tuple->args as $arg ) {
         $values[] = $this->visitExpr( $arg->expr );
      }

      if ( count( $values ) > 1 || ( count( $tuple->args ) === 1 &&
         $tuple->args[ 0 ]->name !== '' ) ) {
         $alloc = new CAlloc();
         $alloc->struct = $this->moduleWalk->getCStruct( $tuple->structure );

         $count = 0;
         foreach ( $tuple->structure->members as $member ) {
            $initz = new CAllocInitializer();
            $initz->memberInt = $count;
            $initz->comment = $member->name;
            $initz->value = $values[ $count ]->var;
            if ( $this->isRefMember( $member ) ) {
               $initz->incRefCount = true;
            }
            $alloc->initializers[] = $initz;
            ++$count;
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
      else {
         $result = new Result();
         $result->var = $values[ 0 ]->var;
         return $result;
      }

   }

   private function visitGeneric( \Generic $generic ): Result {
      switch ( $this->describe( $generic->computedValue->type ) ) {
      case Description::INT:
         return $this->visitIntegerLiteral( $generic->computedValue->inhabitant );
      case Description::STRUCT_TYPE:
         return $this->visitStruct( $generic->computedValue->inhabitant->structure );
      default:
         var_dump( $this->describe( $generic->computedValue->type ) );
         throw new \Exception();
      }
   }

   private function visitDrop( \DropExpr $drop ): Result {
      foreach ( $drop->values as $value ) {
         $result = $this->exprWalker->visitExpr( $value->expr );
         $stmt = new CFreeStmt();
         $stmt->var = $result->var;
         $this->append( $stmt );
      }

      $result = new Result();
      return $result;
   }

   public function visitExpr( \Expr $expr ): Result {
      return $this->exprWalker->visitExpr( $expr );
   }
}
