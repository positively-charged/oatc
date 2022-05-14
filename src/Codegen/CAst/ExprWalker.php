<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

use Node;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description as Desc;
use Typing\InstanceChecker;
use Typing\InstanceCheckerUsage;
use Typing\Presenter;
use Typing\Type;

class Group {
   /** @var CNode[] */
   public array $operations = [];
}

class ExprWalker {
   use DescriberUsage;
   use InstanceCheckerUsage;

   private CodegenTask $task;
   private CTranslationUnit $unit;
   private ?cExpr $cExpr;
   private ScopeList $scopeList;
   private ?CCompoundStmt $compoundStmt;
   /** @var Group[] */
   private array $parentGroups;
   private Group $group;

   public function __construct(
      private Describer $typeDescriber,
      private InstanceChecker $instanceChecker,
      private Presenter $presenter,
      private ModuleWalk $moduleWalk,
      private StackFrame $stackFrame,
      CodegenTask $task,
      CTranslationUnit $unit,
      ScopeList $scopeList ) {
      $this->task = $task;
      $this->unit = $unit;
      $this->cExpr = null;
      $this->scopeList = $scopeList;
      $this->compoundStmt = null;
      $this->group = new Group();
      $this->parentGroups = [];
   }

   public function visitExpr( \Expr $expr ): Result {
      /*
            if ( $expr->constant ) {
               $cExpr = new CExpr();
               $cLiteral = new CIntegerLiteral();
               $cLiteral->value = 0;
               $cExpr->root = $cLiteral;
               if ( $expr->type->structure && $expr->type->structure->name == 'Int' ) {
                  $cLiteral->value = $expr->value;
               }
            }
            else {*/
      $result = $this->visitExprRoot( $expr->root );

      if ( $result->var !== null ) {
         $this->stackFrame->release( $result->var );
      }

      // }

      return $result;
   }

   public function visitExprRoot( Node $node ): Result {
      if ( $node instanceof \BlockStmt ) {
         return $this->visitBlockStmt( $node );
      }
      else if ( $node instanceof \IfStmt ) {
         return $this->visitIf( $node );
      }
      else if ( $node instanceof \WhileStmt ) {
         return $this->visitWhile( $node );
      }
      else if ( $node instanceof \ReturnStmt ) {
         return $this->visitReturnStmt( $node );
      }
      else {
         $simpleExprWalker = new SimpleExprWalker(
            $this->typeDescriber, $this->instanceChecker,
            $this->presenter, $this->moduleWalk, $this,
            $this->stackFrame, $this->compoundStmt, $this->task, $this->unit,
            $this->scopeList );
         return $simpleExprWalker->visitSimpleExpr( $node );
      }
   }

   public function visitBlockStmt( \BlockStmt $stmt ): Result {
      $result = new Result();

      $this->scopeList->push();
      /*
      $cStmt = new CCompoundStmt();
      $prevCompoundStmt = $this->compoundStmt;
      $this->compoundStmt = $cStmt;
      */

      foreach ( $stmt->stmts as $childStmt ) {
         $result = $this->visitExprStmt( $childStmt );
         if ( $this->describe( $childStmt->result->type ) === Desc::NEVER ||
            $this->describe( $childStmt->result->type ) === Desc::ERR ) {
            break;
         }
      }
      $scope = $this->scopeList->pop();

      // Transfer values.
      foreach ( $scope->bindings as $deadBinding ) {
         if ( $deadBinding->var !== null ) {
            $binding = $this->scopeList->get( $deadBinding->name );
            if ( $binding !== null ) {
               $assignment = new CAssignment();
               $assignment->lside = $binding->var;
               $assignment->rside = $deadBinding->var;
               $this->append( $assignment );
            }
         }
      }

      // Release bound variables.
      foreach ( $scope->bindings as $binding ) {
         if ( $binding->var !== null ) {
            $this->stackFrame->release( $binding->var );
         }
      }

      //$this->addCleanup( $cStmt, $scope );
      //$this->compoundStmt = $prevCompoundStmt;
      //array_push( $this->compoundStmt->items, ... $cStmt->items );

      if ( $stmt->returnValueExprStmt === null ) {
         $result = new Result();
      }

      return $result;
   }

   public function visitTopBlockStmt( \Func $func,
      CFunc $cFunc ): CCompoundStmt {
      $items = $this->addGroup();
      $this->scopeList->push();

      $cStmt = new CCompoundStmt();
      $cStmt->items = $items;
      $prevCompoundStmt = $this->compoundStmt;
      $this->compoundStmt = $cStmt;

      foreach ( $cFunc->params as $param ) {
         $binding = $this->scopeList->create( $param->name );
         $binding->var = $param->var;
      }

      $result = $this->visitBlockStmt( $func->body );
      $cStmt->returnValue = $result->var;

      if ( $this->describe( $func->returnType ) === Desc::ENUM ) {
         $cStmt->returnValue = $this->wrapValue( $result,
            $func->returnType->enumeration,
            $func->body->returnValueExprStmt->expr->type );
      }

/*
      foreach ( $stmt->stmts as $childStmt ) {
         $childCStmt = $this->visitExprStmt( $childStmt );
         //$cStmt->items[] = $childCStmt;
         if ( $this->describe( $childStmt->result->type ) === Desc::NEVER ||
            $this->describe( $childStmt->result->type ) === Desc::ERR ) {
            break;
         }
      }
*/

      $scope = $this->scopeList->pop();
      $this->addCleanup( $cStmt, $scope );
      $this->compoundStmt = $prevCompoundStmt;

      $this->popGroup();
      return $cStmt;
   }

   private function wrapValue( Result $value,
      \Enumeration $enumeration, Type $type ): CVar {
      $enumerator = $this->instanceChecker->findEnumerator( $enumeration,
         $type );

      $struct = $this->unit->structs->get( $enumeration->index );
      $unionValue = new CUnionValue();
      $unionValue->struct = $struct;
      $unionValue->member = $enumerator->index;
      $unionValue->value = $value->var;
      $unionValue->result = $this->stackFrame->allocVar();
      $unionValue->result->type->spec = SPEC_STRUCTPTR;
      $unionValue->result->type->struct = $struct;
      $this->append( $unionValue );
      return $unionValue->result;
   }

   private function visitExprStmt( \ExprStmt $stmt ): Result {
      return $this->visitExpr( $stmt->expr );
   }

   private function visitIf( \IfStmt $expr ): Result {
      if ( $expr->err != null ) {
         $cStmt = new CErrStmt();
         $cStmt->message = $expr->err->message;
         return $cStmt;
      }

      foreach ( $expr->ifs as $ifItem ) {
         $topStmt = new CIfStmt();
         $cond = $this->visitExpr( $ifItem->cond );
         $topStmt->cond = $cond->var;
         $topStmt->body = $this->addGroup();
         $this->visitBlockStmt( $ifItem->body );
         $this->popGroup();
         $this->append( $topStmt );
      }

      if ( $expr->elseBody !== null ) {
         $topStmt->elseBody = $this->addGroup();
         $this->visitBlockStmt( $expr->elseBody );
         $this->popGroup();
      }
      /*
      $cStmt = $topStmt;

      foreach ( $expr->elifs as $elif ) {
         $childStmt = new CIfStmt();
         $childStmt->cond = $this->visitExpr( $elif->cond );
         $childStmt->body = $this->visitBlockStmt( $elif->body );
         $compound = new CCompoundStmt();
         array_push( $compound->items, $childStmt );
         $cStmt->elseBody = $compound;
         $cStmt = $childStmt;
      }
      if ( ! is_null( $expr->elseBody ) ) {
         $cStmt->elseBody = $this->visitBlockStmt( $expr->elseBody );
      }
      */


      $result = new Result();

      return $result;
   }

   private function visitWhile( \WhileStmt $expr ): Result {
      $stmt = new CWhileStmt();

      $condGroup = $this->addGroup();
      $cond = $this->visitExpr( $expr->cond );
      $this->popGroup();

      $stmt->condGroup = $condGroup;
      $stmt->cond = $cond->var;

      $bodyGroup = $this->addGroup();
      $result = $this->visitBlockStmt( $expr->body );
      $stmt->body = $bodyGroup;
      $this->popGroup();

      $this->append( $stmt );
      return new Result();
   }

   private function visitReturnStmt( \ReturnStmt $stmt ): Result {
      $result = new Result();
      $cStmt = new CReturnStmt();

      if ( $stmt->value !== null ) {
         $cStmt->value = $this->visitExpr( $stmt->value )->var;

         // Increase ref count.
         /*
         if ( $stmt->value->type->spec == TYPESPEC_STRUCT &&
            ! $this->isPrimitiveStruct( $stmt->value->type->structure ) ) {
            if ( $cStmt->value->alloc ) {
               $cStmt->value->alloc->externalRefs = true;
            }
            $access = new CDeref();
            $access->member = 'rc';
            $access->operand = $cStmt->value->root;
            $unary = new CUnary();
            $unary->op = CUOP_PRE_INC;
            $unary->operand = $access;
            $expr = new CExpr();
            $expr->root = $unary;
            $cExprStmt = new CExprStmt();
            $cExprStmt->expr = $expr;
            $cCompound = new CCompoundStmt();
            array_push( $cCompound->items, $cExprStmt );
            array_push( $cCompound->items, $cStmt );
            $cStmt = $cCompound;
         }*/
      }

      $this->append( $cStmt );
      return $result;
   }

   public function append( CNode $operation ): void {
      $this->group->operations[] = $operation;
   }

   private function addCleanup( CCompoundStmt $stmt, Scope $scope ): void {
      $cleanup = new CCompoundStmt();
      $stmt->cleanup = $cleanup;
      /*
      foreach ( $scope->allocs as $alloc ) {
         if ( $alloc->borrowed ) {
            array_push( $stmt->allocs, $alloc );
         }
         else if ( $alloc->numLabelsAttached == 0 ) {
            array_push( $stmt->allocs, $alloc );
            if ( $alloc->externalRefs ) {
               $cleanupStmt = new CCleanupStmt();
               $cleanupStmt->alloc = $alloc;
               $cleanupStmt->struct = $alloc->struct;
               array_unshift( $cleanup->items, $cleanupStmt );
            }
            else {
               $usage = new CNameUsage();
               if ( $alloc->struct->cleanupFunc != null ) {
                  $usage->name = $alloc->struct->cleanupFunc->name;
               }
               else {
                  $usage->name = 'free';
               }
               $call = new CCall();
               $call->func = CCALL_OPERAND;
               $call->operand = $usage;
               $expr = new CExpr();
               $expr->root = $alloc;
               array_push( $call->args, $expr );
               $expr = new CExpr();
               $expr->root = $call;
               $exprStmt = new CExprStmt();
               $exprStmt->expr = $expr;

               array_unshift( $cleanup->items, $exprStmt );
            }
         }
      }
      */
   }

   public function addGroup(): Group {
      $this->parentGroups[] = $this->group;
      $this->group = new Group();
      return $this->group;
   }

   public function popGroup(): void {
      if ( count( $this->parentGroups ) > 0 ) {
         $this->group = array_pop( $this->parentGroups );
      }
      else {
         throw new \Exception();
      }
   }
}
