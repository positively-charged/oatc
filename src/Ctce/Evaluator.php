<?php

/**
 * Compile-time code evaluation (CTCE).
 */

declare( strict_types = 1 );

namespace Ctce;

require_once CTCE_DIR . '/Scope.php';

use Binding;
use Checking\BuiltinModule;
use Checking\Value;
use Exception;
use Func;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description as Desc;
use Typing\Presenter;
use Typing\PresenterUsage;
use \Typing\Type;
use \Typing\TypeChecker;
use \Slot;

const STATE_STOPPED = 0;
const STATE_RUNNING = 1;
const STATE_RETURNING = 2;
const STATE_BREAKING = 3;
const STATE_CONTINUING = 4;

class Evaluator {
   use PresenterUsage;
   use DescriberUsage;

   private \User $user;
   private \Scope $scope;
   private ScopeTable $scopeTable;
   public TypeChecker $typeChecker;
   private Result $emptyValue;
   private Result $returnValue;
   private int $state;

   public function __construct( \User $user, \Scope $scope,
      private BuiltinModule $builtinModule,
      TypeChecker $typeChecker,
      private Describer $typeDescriber,
      private Presenter $typePresenter ) {
      $this->user = $user;
      $this->scope = $scope;
      $this->scopeTable = new ScopeTable();
      $this->typeChecker = $typeChecker;
      $this->emptyValue = new Result();
      $this->returnValue = $this->emptyValue;
      $this->state = STATE_STOPPED;
   }

   public function evalVirtExpr( \Expr $expr ): void {
      $this->state = STATE_RUNNING;
      $result = $this->evalExpr( $expr );
      if ( $result->slot != null ) {
         $expr->type = $result->slot->type;
         $expr->value = $result->slot->value;
      }
      $expr->constant = true;
   }

   private function evalBlockStmt( \BlockStmt $stmt ): Result {
      $this->scope->enter();
      $this->evalStmtList( $stmt );
      $this->scope->leave();
      $result = new Result();
      if ( $this->state == STATE_RUNNING ) {
         $result->slot = $this->returnValue->slot;
      }
      return $result;
   }

   private function evalStmtList( \BlockStmt $stmt ): void {
      foreach ( $stmt->stmts as $stmt ) {
         if ( $this->state == STATE_RUNNING ) {
            $this->evalStmt( $stmt );
         }
         else {
            break;
         }
      }
   }

   private function evalStmt( \Node $stmt ): void {
      if ( $stmt instanceof \ExprStmt ) {
         $this->evalExprStmt( $stmt );
      }
      else {
         printf( "error: unhandled node: %s\n", get_class( $stmt ) );
         exit( 1 );
      }
   }

   private function evalIfStmt( \IfStmt $stmt ): Result {
      $this->scope->enter();

      $body = null;
      foreach ( $stmt->ifs as $if ) {
         $cond = $this->evalExpr( $if->cond );
         if ( $cond->slot->value ) {
            $body = $if->body;
            break;
            $body = $this->evalBlockStmt( $stmt->body );
         }
      }


      $slot = null;


      if ( $body !== null ) {
         $body = $this->evalBlockStmt( $body );
         $slot = $body->slot;
      }
      else {
         if ( $stmt->elseBody !== null ) {
            $body = $this->evalBlockStmt( $stmt->elseBody );
            $slot = $body->slot;
         }
      }

      $result = new Result();
      $result->slot = $slot;
      $this->scope->leave();

      return $result;
   }

   private function evalWhile( \WhileStmt $stmt ): Result {
      while ( $this->running() ) {
         $this->scope->enter();
         if ( $this->whileCondIsTrue( $stmt ) ) {
            $this->evalBlockStmt( $stmt->body );
            if ( $this->state == STATE_CONTINUING ) {
               $this->state = STATE_RUNNING;
            }
         }
         else {
            break;
         }
         $this->scope->leave();
      }

      switch ( $this->state ) {
      case STATE_BREAKING:
         $this->state = STATE_RUNNING;
         break;
      case STATE_RUNNING:
         if ( $stmt->endfully != null ) {
            $this->evalBlockStmt( $stmt->endfully );
         }
         break;
      }

      $result = new Result();
      return $result;
   }

   private function whileCondIsTrue( \WhileStmt $stmt ): bool {
      $cond = $this->evalExpr( $stmt->cond );
      return ( $cond->slot->value == true );
   }

   private function running(): bool {
      return ( $this->state == STATE_RUNNING );
   }

   private function evalFor( \ForLoop $loop ): Result {
      $this->scope->enter();
      $collection = $this->evalExpr( $loop->collection );
      $iter = $collection->slot->value->iterate();
      $binding = null;
      if ( $loop->item != null ) {
         $binding = $this->scope->createBinding( $loop->item->name );
      }
      while ( $this->running() ) {
         $item = $iter->next();
         if ( $item != null ) {
            if ( $binding != null ) {
               $binding->slot = $item->slot;
            }
            $this->evalBlockStmt( $loop->body );
            if ( $this->state == STATE_CONTINUING ) {
               $this->state = STATE_RUNNING;
            }
         }
         else {
            break;
         }
      }

      switch ( $this->state ) {
      case STATE_BREAKING:
         $this->state = STATE_RUNNING;
         break;
      case STATE_RUNNING:
         if ( $loop->endfully != null ) {
            $this->evalBlockStmt( $loop->endfully );
         }
         break;
      }

      $this->scope->leave();
      $result = new Result();
      return $result;
   }

   private function evalJump( \Jump $jump ): Result {
      if ( $jump->type == JUMP_BREAK ) {
         $this->state = STATE_BREAKING;
      }
      else {
         $this->state = STATE_CONTINUING;
      }
      $result = new Result();
      return $result;
   }

   private function evalReturn( \ReturnStmt $stmt ): Result {
      if ( $stmt->value !== null ) {
         $this->returnValue = $this->evalExpr( $stmt->value );
      }
      $this->state = STATE_RETURNING;
      $result = new Result();
      return $result;
   }

   private function evalExprStmt( \ExprStmt $stmt ): void {
      $result = $this->evalExpr( $stmt->expr );
      if ( $this->state == STATE_RUNNING ) {
         if ( $stmt->yield ) {
            $this->returnValue = $result;
         }
         else {
            $this->returnValue = $this->emptyValue;
         }
      }
   }

   private function evalExpr( \Expr $expr ): Result {
      return $this->evalRoot( $expr->root );
   }

   private function evalRoot( \Node $node ): Result {
      if ( $node instanceof \Let ) {
         return $this->evalBinding( $node );
      }
      else if ( $node instanceof \BlockStmt ) {
         return $this->evalBlockStmt( $node );
      }
      else if ( $node instanceof \IfStmt ) {
         return $this->evalIfStmt( $node );
      }
      else if ( $node instanceof \WhileStmt ) {
         return $this->evalWhile( $node );
      }
      else if ( $node instanceof \ForLoop ) {
         return $this->evalFor( $node );
      }
      else if ( $node instanceof \Jump ) {
         return $this->evalJump( $node );
      }
      else if ( $node instanceof \ReturnStmt ) {
         return $this->evalReturn( $node );
      }
      else if ( $node instanceof \Assignment ) {
         return $this->evalAssignment( $node );
      }
      else if ( $node instanceof \Binary ) {
         return $this->evalBinary( $node );
      }
      else if ( $node instanceof \Logical ) {
         return $this->evalLogical( $node );
      }
      else {
         return $this->evalPrefix( $node );
      }
   }

   private function evalBinding( \Let $localBinding ): Result {
      $result = $this->evalExpr( $localBinding->value );
      $binding = $this->scope->get( $localBinding->name );
      if ( $binding !== null ) {
         $binding->slot = $result->slot;
         $result->binding = $binding;
         return $result;
      }
      else {
         throw new Exception();
      }
   }

   private function evalAssignment( \Assignment $assignment ): Result {
      $lside = $this->evalRoot( $assignment->lside );
      $rside = $this->evalRoot( $assignment->rside );
      $lside->binding->slot = $rside->slot;
      return $lside;
   }

   private function evalBinary( \Binary $binary ): Result {
      $lside = $this->evalRoot( $binary->lside );
      $rside = $this->evalRoot( $binary->rside );
      switch ( $this->describe( $lside->slot->type ) ) {
      case Desc::BOOL:
         return $this->evalBinaryBool( $binary, $lside, $rside );
      case Desc::INT:
         return $this->evalBinaryInt( $binary, $lside, $rside );
      case Desc::STR:
         return $this->evalBinaryStr( $binary, $lside, $rside );
      case Desc::STRUCT_TYPE:
         return $this->evalBinaryStructType( $binary, $lside, $rside );
      }
      //var_dump($binary);
   }

   private function evalBinaryBool( \Binary $binary, Result $lside,
      Result $rside ): Result {
      $slot = new Slot();
      $slot->type = $binary->type;
      switch ( $binary->op ) {
      case \Binary::OP_EQ:
         $slot->value = ( $lside->slot->value == $rside->slot->value );
         break;
      case \Binary::OP_NEQ:
         $slot->value = ( $lside->slot->value != $rside->slot->value );
      }
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalBinaryInt( \Binary $binary, Result $lside,
      Result $rside ): Result {
      $slot = new Slot();
      switch ( $binary->op ) {
      case \Binary::OP_ADD:
         $slot->value = $lside->slot->value + $rside->slot->value;
         $slot->type = $this->typeChecker->createIntType();
         break;
      case \Binary::OP_SUB:
         $slot->value = $lside->slot->value - $rside->slot->value;
         $slot->type = $this->typeChecker->createIntType();
         break;
      case \Binary::OP_EQ:
         $slot->value = ( $lside->slot->value == $rside->slot->value );
         $slot->type = $binary->type;
         break;
      case \Binary::OP_NEQ:
         $slot->value = ( $lside->slot->value != $rside->slot->value );
         $slot->type = $binary->type;
         break;
      case \Binary::OP_LT:
         $slot->value = ( $lside->slot->value < $rside->slot->value );
         $slot->type = $binary->type;
         break;
      case \Binary::OP_LTE:
         $slot->value = ( $lside->slot->value <= $rside->slot->value );
         $slot->type = $binary->type;
         break;
      case \Binary::OP_GT:
         $slot->value = ( $lside->slot->value > $rside->slot->value );
         $slot->type = $binary->type;
         break;
      case \Binary::OP_GTE:
         $slot->value = ( $lside->slot->value >= $rside->slot->value );
         $slot->type = $binary->type;
         break;
      default:
         throw new \Exception();
      }
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalBinaryStr( \Binary $binary, Result $lside,
      Result $rside ): Result {
      $slot = new Slot();
      switch ( $binary->op ) {
      case \Binary::OP_EQ:
         $slot->value = ( $lside->slot->value == $rside->slot->value );
         $slot->type = $binary->type;
         break;
      case \Binary::OP_NEQ:
         $slot->value = ( $lside->slot->value != $rside->slot->value );
         $slot->type = $binary->type;
         break;
      default:
         UNREACHABLE();
      }
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalBinaryStructType( \Binary $binary, Result $lside,
      Result $rside ): Result {
      $slot = new Slot();
      switch ( $binary->op ) {
      case \Binary::OP_EQ:
         $slot->value = ( $lside->slot->type->structure ===
            $rside->slot->type->structure );
         $slot->type = $this->typeChecker->createBoolType();
         break;
      case \Binary::OP_NEQ:
         $slot->value = ( $lside->slot->type->structure !==
            $rside->slot->type->structure );
         $slot->type = $this->typeChecker->createBoolType();
      }
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalLogical( \Logical $logical ): Result {
      $lside = $this->evalRoot( $logical->lside );
      if ( $lside->slot->value == true ) {
         if ( $logical->operator == \Logical::OPERATOR_AND ) {
            return $this->evalRoot( $logical->rside );
         }
         else {
            return $lside;
         }
      }
      else {
         if ( $logical->operator == \Logical::OPERATOR_OR ) {
            return $this->evalRoot( $logical->rside );
         }
         else {
            return $lside;
         }
      }
   }

   private function evalPrefix( \Node $node ): Result {
      if ( $node instanceof \Unary ) {
         return $this->evalUnary( $node );
      }
      else if ( $node instanceof \LogicalNot ) {
         return $this->evalLogicalNot( $node );
      }
      else {
         return $this->evalPossibleLike( $node );
      }
   }

   private function evalUnary( \Unary $unary ): Result {
      switch ( $unary->op ) {
      case UOP_PRE_INC:
      case UOP_PRE_DEC:
         return $this->evalIncrement( $unary );
      default:
         throw new \Exception();
      }
   }

   private function evalIncrement( \Unary $unary ): Result {
      $operand = $this->evalPrefix( $unary->operand );
      switch ( $unary->op ) {
      case UOP_PRE_INC:
         ++$operand->binding->slot->value;
         break;
      case UOP_PRE_DEC:
         --$operand->binding->slot->value;
         break;
      }
      return $operand;
   }

   private function evalLogicalNot( \LogicalNot $logicalNot ): Result {
      $operand = $this->evalRoot( $logicalNot->operand );
      $slot = new Slot();
      $slot->type = $this->typeChecker->createBoolType();
      $slot->value = $operand->slot->value ? false : true;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalPossibleLike( \Node $node ): Result {
      if ( $node instanceof \Like ) {
         return $this->evalLike( $node );
      }
      else {
         return $this->evalSuffix( $node );
      }
   }

   private function evalLike( \Like $like ): Result {
      $operand = $this->evalRoot( $like->operand );
      $value = false;
      switch ( $like->pattern->match ) {
      case MATCH_ENUM_TAG:
         ASSERT_( $operand->slot->value instanceof EnumInstance );
         $value = ( $operand->slot->value->enumerator ===
            $like->pattern->enumerator );
         break;
      case MATCH_ENUMERATOR:
         ASSERT_( $operand->slot->value instanceof EnumInstance );
         $value = ( $operand->slot->value->enumerator ===
            $like->pattern->enumerator );
         if ( $value ) {
            foreach ( $like->pattern->args as $i => $arg ) {
               if ( ! $this->match( $operand->slot->value->params[ $i ]->slot,
                  $arg ) ) {
                  $value = false;
                  break;
               }
            }
         }
         break;
      default:
         throw new \Exception();
      }
      $slot = new Slot();
      $slot->type = $this->typeChecker->createBoolType();
      $slot->value = $value;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function match( Slot $slot, \Pattern $pattern ): bool {
      switch ( $pattern->type ) {
      case PATTERN_INTEGER_LITERAL:
         return ( $slot->value === $pattern->integerLiteral->value );
      case PATTERN_BOOL_LITERAL:
         return ( $slot->value === $pattern->boolLiteral->value );
      default:
         throw new \Exception();
      }
   }

   private function evalSuffix( \Node $node ): Result {
      if ( $node instanceof \Call ) {
         return $this->evalCall( $node );
      }
      else if ( $node instanceof \Access ) {
         return $this->evalAccess( $node );
      }
      else {
         return $this->evalPrimary( $node );
      }
   }

   private function evalCall( \Call $call ): Result {
      switch ( $call->type ) {
      case \CALL_ENUM:
         return $this->evalEnumCall( $call );
      case \CALL_STRUCTURE:
         return $this->evalStructCall( $call );
      case \CALL_FUNC:
         return $this->evalFuncCall( $call );
      default:
         throw new \Exception();
      }
   }

   private function evalEnumCall( \Call $call ): Result {
      $instance = new EnumInstance();
      $instance->enumerator = $call->enumerator;
      $count = 0;
      foreach ( $call->enumerator->params as $i => $param ) {
            $binding = new \Binding();
            $binding->name = $param->name;
            if ( $count < count( $call->args ) ) {
               $result = $this->evalExpr( $call->args[ $count ] );
               $binding->slot = $result->slot;
            }
         else {
            $result = $this->evalExpr( $param->defaultArg );
            $binding->slot = $result->slot;
         }
         #$instance->params[ $param->name ] = $binding;
         $instance->params[ $i ] = $binding;
         ++$count;
      }

      $slot = new Slot();
      $slot->type = new Type();
      $slot->type->spec = TYPESPEC_ENUM;
      $slot->type->enumeration = $call->enumerator->enumeration;
      $slot->value = $instance;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalStructCall( \Call $call ): Result {
      switch ( $call->structure->builtin ) {
      case BUILTIN_STRUCTURE_NONE:
      case BUILTIN_STRUCTURE_STRUCT:
         break;
      case BUILTIN_STRUCTURE_VEC:
         return $this->evalVecStructCall( $call );
      default:
         throw new \Exception();
      }
      $instance = new StructInstance();
      $instance->structure = $call->structure;
      $count = 0;
      foreach ( $call->structure->members as $member ) {
         $binding = new \Binding();
         $binding->name = $member->name;
         if ( $count < count( $call->args ) ) {
            $result = $this->evalExpr( $call->args[ $count ] );
            $binding->slot = $result->slot;
         }
         else {
            $result = $this->evalExpr( $member->defaultInitializer );
            $binding->slot = $result->slot;
         }
         $instance->members[ $member->name ] = $binding;
         ++$count;
      }

      $slot = new Slot();
      $slot->type = new Type();
      $slot->type->spec = TYPESPEC_STRUCT;
      $slot->type->structure = $call->structure;
      $slot->value = $instance;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalVecStructCall( \Call $call ): Result {
      $instance = new VecInstance();
      foreach ( $call->args as $arg ) {
         $result = $this->evalExpr( $arg );
         array_push( $instance->values, $result->slot );
         ++$instance->size;
      }
      $slot = new Slot();
      $slot->type = new Type();
      $slot->type->spec = TYPESPEC_STRUCT;
      $slot->type->structure = $call->structure;
      $slot->value = $instance;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalFuncCall( \Call $call ): Result {
      $operand = $this->evalSuffix( $call->operand );
      $args = [];
      if ( $call->method ) {
         $args[] = $operand;
      }
      foreach ( $call->args as $arg ) {
         $result = $this->evalExpr( $arg->expr );
         /*
         if ( $result->slot->type->spec == TYPESPEC_STRUCT_TYPE ) {
            $result = $this->createStructTypeResult(
               $result->slot->type->struct );
         } */
         $args[] = $result;
      }
      $this->returnValue = $this->emptyValue;
      if ( $call->func->internal ) {
         $this->evalInternFunc( $call, $args );
      }
      else {
         $this->evalUserDefinedFunc( $call, $args );
      }
      return $this->returnValue;
   }

   private function createStructTypeResult( \Structure $structure ): Result {
      $args = [];
      $infoStruct = $this->scope->get( 'Struct' )?->node;
      foreach ( $infoStruct->members as $member ) {
         $slot = new Slot();
         $slot->type = $member->type;
         switch ( $member->name ) {
         case 'name': $slot->value = $structure->name; break;
         case 'size': $slot->value = $structure->size; break;
         case 'members':
            $slot->value = $this->createMembersVec( $structure );
            break;
         default:
            UNREACHABLE( 'unhandled member `%s`', $member->name );
         }
         $result = new Result;
         $result->slot = $slot;
         array_push( $args, $result );
      }
      $slot = $this->createStructInstance( $infoStruct, $args );
      #var_dump( $instance );
      #var_dump( $structure->name );
      #var_dump( $structure->size );

      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function createMembersVec( \Structure $structure ): VecInstance {
      $instance = new VecInstance();

      $infoStruct = $this->scope->get( 'StructMember' )?->node;
      foreach ( $structure->members as $member ) {
         $args = [];
         foreach ( $infoStruct->members as $infoMember ) {
            $slot = new Slot();
            $slot->type = $infoMember->type;
            switch ( $infoMember->name ) {
            case 'name': $slot->value = $member->name; break;
            default:
               UNREACHABLE( 'unhandled member `%s`', $infoMember->name );
            }
            $result = new Result;
            $result->slot = $slot;
            $args[] = $result;
         }
         $slot = $this->createStructInstance( $infoStruct, $args );
         $instance->values[] = $slot;
         ++$instance->size;
      }

      /*
      foreach ( $call->args as $arg ) {
         $result = $this->evalExpr( $arg );
         array_push( $instance->values, $result->slot );
         ++$instance->size;
      }*/
      return $instance;
   }

   /** @param $args Result[] */
   private function createStructInstance( \Structure $structure,
      array $args ): Slot {
      $instance = new StructInstance();
      $instance->structure = $structure;
      $count = 0;
      foreach ( $structure->members as $member ) {
         $binding = new \Binding();
         $binding->name = $member->name;
         if ( $count < count( $args ) ) {
            $result = $args[ $count ];
            $binding->slot = $result->slot;
         }
         else {
            $result = $this->evalExpr( $member->defaultInitializer );
            $binding->slot = $result->slot;
         }
         $instance->members[ $member->name ] = $binding;
         ++$count;
      }

      $slot = new Slot();
      $slot->type = new Type();
      $slot->type->spec = TYPESPEC_STRUCT;
      $slot->type->structure = $structure;
      $slot->value = $instance;

      return $slot;
   }

   /**
    * @param $args Result[]
    */
   private function evalInternFunc( \Call $call, array $args ): void {
      switch ( $call->func->builtin ) {
      case \Func::BUILTIN_BAIL:
         $this->user->diag( DIAG_NONE, $call->pos,
            "aborted by user request" );
         $this->user->bail();
         break;
      case \Func::BUILTIN_ASSERT:
         if ( count( $args ) == 1 ) {
            if ( ! $args[ 0 ]->slot->value ) {
               printf( "%s:%d:%d: assertion failure\n",
                  $call->pos->file,
                  $call->pos->line,
                  $call->pos->column );
               exit( 1 );
            }
         }
         break;
      case \Func::BUILTIN_PRINT:
         $this->callPrint( $call, $args );
         break;
      case \Func::BUILTIN_PRINTLN:
         $this->callPrint( $call, $args, newline: true );
         break;
      case \Func::BUILTIN_DUMP:
         $this->callDump( $call, $args );
         break;
      default:
         UNREACHABLE( 'unimplemented internal function (function ID: %d)',
            $call->func->builtin );
      }
   }

   /** @param $args Result[] */
   private function callPrint( \Call $call, array $args,
      bool $newline = false ): void {
      foreach ( $args as $arg ) {
         switch ( $arg->slot->type->spec ) {
         case TYPESPEC_STRUCT:
            switch ( $arg->slot->type->structure->builtin ) {
            case BUILTIN_STRUCTURE_INT:
               printf( "%d", $arg->slot->value );
               break;
            case BUILTIN_STRUCTURE_BOOL:
               printf( "%s", $arg->slot->value ? "true" : "false" );
               break;
            case BUILTIN_STRUCTURE_STR:
               echo str_replace( '\n', "\n", $arg->slot->value );
               break;
            default:
               $this->printStruct( $arg );
            }
            break;
         default:
            throw new \Exception();
         }
      }
      if ( $newline ) {
         echo "\n";
      }
   }

   private function printStruct( Result $arg ): void {
      switch ( $arg->slot->type->structure->name ) {
      case 'Int':
         printf( '%d', $arg->slot->value );
         break;
      case 'Bool':
         printf( '%s', $arg->slot->value ? "true" : "false" );
         break;
      case 'Str':
         echo str_replace( '\n', "\n", $arg->slot->value );
         break;
      default:
         $this->printCustomStruct( $arg );
         break;
      }
   }

   private function printCustomStruct( Result $arg ): void {
      foreach ( $arg->slot->type->structure->impls as $impl ) {
         if ( $impl->traitName === 'Show' ) {
            $func = $impl->findFunc( 'show' );
            $this->callUserFunc( $func, [ $arg ] );
            return;
         }
      }
      if ( $arg->slot->value instanceof StructInstance ) {
         printf( "struct: %s\n", $arg->slot->value->structure->name );
         $members = [];
         foreach ( $arg->slot->value->members as $name => $binding ) {
            $members[ $name ] = $binding->slot->value;
         }
         var_dump( $members );
      }
      else {
         var_dump( $arg->slot->type->structure->name );
         throw new \Exception();
      }
   }

   /**
    * @param $args Result[]
    */
   private function callDump( \Call $call, array $args ): void {
      foreach ( $args as $arg ) {
         $text = $this->presentType( $arg->slot->type );
         $this->user->diag( DIAG_NONE, $call->pos, "%s", $text );
      }
   }

   /**
    * @param $args Result[]
    */
   private function evalUserDefinedFunc( \Call $call, array $args ): void {
      $this->callUserFunc( $call->func, $args );
   }

   private function callUserFunc( Func $func, array $args ): void {
      $this->scope->enter();

      $count = 0;
      foreach ( $func->params as $param ) {
         $binding = $this->scope->createBinding( $param->name );
         $binding->node = $param;
         if ( $count < count( $args ) ) {
            $binding->slot = $args[ $count ]->slot;
         }
         else {
            if ( $param->defaultArg !== null ) {
               $slot = new Slot();
               $slot->value = $param->defaultArg->value;
               $slot->type = $param->type;
               $binding->slot = $slot;
            }
            else {
               throw new Exception( 'missing argument for function' );
            }
         }
         ++$count;
      }

      if ( $func->argsParam != null ) {
         $instance = new VecInstance();
         while ( $count < count( $args ) ) {
            array_push( $instance->values, $args[ $count ]->slot );
            ++$instance->size;
            ++$count;
         }
         $slot = new Slot();
         $slot->type = new Type();
         $slot->type->spec = TYPESPEC_STRUCT;
         $slot->type->structure = $func->argsParam->type->structure;
         $slot->value = $instance;
         $binding = $this->scope->createBinding(
            $func->argsParam->name );
         $binding->slot = $slot;
      }

      $this->evalFuncBlockStmt( $func->body );

      $this->scope->leave();
   }

   private function evalFuncBlockStmt( \BlockStmt $stmt ): void {
      $this->evalStmtList( $stmt );
   }

   private function evalAccess( \Access $access ): Result {
      switch ( $access->type ) {
      case ACCESS_MEMBER:
         return $this->evalAccessMember( $access );
      case ACCESS_METHOD:
         return $this->evalAccessMethod( $access );
      case ACCESS_STRUCTURE_SIZE:
         $lside = $this->evalSuffix( $access->lside );
         $result = new Result();
         $result->slot = new Slot();
         $result->slot->value = $lside->slot->type->structure->size;
         $result->slot->type = $this->typeChecker->createIntType();
         return $result;
      case ACCESS_STRUCTURE_NAME:
         $lside = $this->evalSuffix( $access->lside );
         $result = new Result();
         $result->slot = new Slot();
         $result->slot->value = $lside->slot->type->structure->name;
         $result->slot->type = $this->typeChecker->createStrType();
         return $result;
      default:
         throw new \Exception();
      }
   }

   private function evalAccessMember( \Access $access ): Result {
      $lside = $this->evalSuffix( $access->lside );
      if ( $lside->slot->value instanceof StructInstance ) {
         $result = new Result();
         $result->binding = $lside->slot->value->members[
            $access->memberName ];
         $result->slot = $result->binding->slot;
         return $result;
      }
      throw new \Exception();
   }

   private function evalAccessMethod( \Access $access ): Result {
      return $this->evalSuffix( $access->lside );
   }

   private function evalPrimary( \Node $node ): Result {
      if ( $node instanceof \NameUsage ) {
         return $this->evalNameUsage( $node );
      }
      else if ( $node instanceof \Structure ) {
         return $this->evalStructLiteral( $node );
      }
      else if ( $node instanceof \IntegerLiteral ) {
         return $this->evalIntegerLiteral( $node );
      }
      else if ( $node instanceof \BoolLiteral ) {
         return $this->evalBoolLiteral( $node );
      }
      else if ( $node instanceof \StringLiteral ) {
         return $this->evalStringLiteral( $node );
      }
      else if ( $node instanceof \TypeLiteral ) {
         return $this->evalTypeLiteral( $node );
      }
      else if ( $node instanceof \Tuple ) {
         return $this->evalParen( $node );
      }
      else {
         var_dump( get_class( $node ) );
         throw new \Exception();
      }
   }

   private function evalNameUsage( \NameUsage $usage ): Result {
      $result = $this->getBinding( $usage );
      if ( count( $usage->args ) > 0 ) {
         return $this->evalNameUsageSubscript( $usage, $result );
      }
      return $result;
   }

   private function evalNameUsageSubscript( \NameUsage $usage,
      Result $lside ): Result {
      switch ( $this->describe( $lside->slot->type ) ) {
      case Desc::STRUCT:
         switch ( $lside->slot->type->structure->builtin ) {
         case BUILTIN_STRUCTURE_VEC:
            return $this->checkVecSubscript( $usage, $lside );
         }
         break;
      case Desc::STRUCT_TYPE:
         return $this->checkStructTypeSubscript( $usage, $lside );
      }
      throw new Exception();
   }

   private function checkVecSubscript( \NameUsage $usage,
      Result $lside ): Result {
      ASSERT_( $lside->slot->value instanceof VecInstance );
      $vec = $lside->slot->value;
      $index = $this->evalExpr( $usage->args[ 0 ] );
      if ( ! ( $index->slot->value >= 0 &&
         $index->slot->value < $vec->size ) ) {
         $this->user->diag( DIAG_ERR, $usage->pos,
            "invalid index" );
         $this->user->bail();
      }

      $result = new Result();
      $result->slot = $vec->values[ $index->slot->value ];
      return $result;
   }

   private function checkStructTypeSubscript( \NameUsage $usage,
      Result $lside ): Result {
      $args = [];
      foreach ( $usage->args as $arg ) {
         $result = $this->evalExpr( $arg );
         $args[] = $result->slot->type;
      }
      $lside->slot->type->args = $args;
      return $lside;
   }

   private function getBinding( \NameUsage $usage ): Result {
      $binding = $this->scope->get( $usage->name, $usage->pos,
         $usage->module );
      ASSERT_( $binding != null );
      if ( $binding->slot != null ) {
         $result = new Result();
         $result->binding = $binding;
         $result->slot = $binding->slot;
         return $result;
      }
      else {
         $slot = new Slot();
         $node = $binding->node;
         if ( $node instanceof \ImportItem ) {
            $node = $node->object;
         }
         if ( $node instanceof \Enumerator ) {
            return $this->evalEnumeratorBinding( $binding );
         }
         else if ( $node instanceof \Structure ) {
            $result = $this->evalStructLiteral( $node );
            $result->binding = $binding;
            $binding->slot = $result->slot;
            return $result;
         }
         else if ( $node instanceof \Constant ) {
            $slot->type = $node->type;
            $slot->value = $node->value->value;
            $binding->slot = $slot;
            $result = new Result();
            $result->binding = $binding;
            $result->slot = $slot;
            return $result;
         }
         else if ( $node instanceof \Variable ) {
            $slot->type = $node->type;
            $slot->value = 0;
            $binding->slot = $slot;
            $result = new Result();
            $result->binding = $binding;
            $result->slot = $slot;
            return $result;
         }
         else if ( $node instanceof \Let || $node instanceof \Param ) {
            return $this->getAstNode( $binding );
         }
         else if ( $node instanceof Func ) {

         }
         else {
            UNREACHABLE( 'unhandled node `%s`', get_class( $node ) );
         }
         $binding->slot = $slot;
         $result = new Result();
         $result->binding = $binding;
         $result->slot = $binding->slot;
         return $result;
      }

      throw new \Exception();
   }

   private function getAstNode( Binding $binding ): Result {
      $slot = new Slot();
      $slot->type = $binding->value->type;
      $slot->value = 0;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalEnumeratorBinding( \Binding $binding ): Result {
      if ( $binding->node instanceof \Enumerator ) {
         if ( $binding->slot == null ) {
            $slot = new Slot();
            //$slot->type = $binding->node->enumeration->baseType;
            $slot->type = $binding->node->type;
            $slot->value = $binding->node->value;
            $binding->slot = $slot;
         }
         $result = new Result();
         $result->binding = $binding;
         $result->slot = $binding->slot;
         return $result;
      }
      throw new \Exception();
   }

   private function evalStructLiteral( \Structure $structure ): Result {
      $slot = new Slot();
      $slot->type = new Type();
      $slot->type->spec = TYPESPEC_STRUCT_TYPE;
      $slot->type->structure = $structure;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalIntegerLiteral( \IntegerLiteral $literal ): Result {
      $slot = new Slot();
      $slot->type = $this->typeChecker->createIntType();
      $slot->type->value = $literal->value;
      $slot->value = $literal->value;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalBoolLiteral( \BoolLiteral $literal ): Result {
      $slot = new Slot();
      $slot->type = $this->typeChecker->createBoolType();
      $slot->value = $literal->value;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalStringLiteral( \StringLiteral $literal ): Result {
      $slot = new Slot();
      $slot->type = $this->typeChecker->createStrType();
      $slot->value = $literal->value;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalTypeLiteral( \TypeLiteral $literal ): Result {
      $slot = new Slot();
      $slot->type = new Type();
      $slot->type->structure = $this->builtinModule->typeStruct;
      $slot->type->spec = TYPESPEC_STRUCT_TYPE;
      $slot->value = $literal->type;
      $result = new Result();
      $result->slot = $slot;
      return $result;
   }

   private function evalParen( \Tuple $paren ): Result {
      foreach ( $paren->args as $expr ) {
         $result = $this->evalExpr( $expr );
      }
      return $result;
   }

   /**
    * @param Value[] $args
    */
   public function expandGeneric( \Generic $generic,
      array $args ): Value {
      $this->state = STATE_RUNNING;
      $this->returnValue = $this->emptyValue;

      $this->scope->enter();

      $count = 0;
      foreach ( $generic->params as $param ) {
         $binding = $this->scope->createBinding( $param->name );
         $binding->node = $param;
         if ( $count < count( $args ) ) {
            $slot = new Slot();
            $slot->type = $args[ $count ]->type;
            $slot->value = $args[ $count ]->inhabitant;
            $binding->slot = $slot;
         }
         else {
            if ( $param->defaultArg !== null ) {
               $slot = new Slot();
               $slot->value = $param->defaultArg->value;
               $slot->type = $param->type;
               $binding->slot = $slot;
            }
            else {
               throw new Exception( 'missing argument for function' );
            }
         }
         ++$count;
      }

      $this->evalFuncBlockStmt( $generic->body );

      $this->scope->leave();


/*
      $result = $this->evalExpr( $expr );
      if ( $result->slot != null ) {
         $expr->type = $result->slot->type;
         $expr->value = $result->slot->value;
      }
      $expr->constant = true;
*/

      $result = new Value();
      $result->type = $this->returnValue->slot->type;
      $result->inhabitant = $this->returnValue->slot->value;

      if ( $result->inhabitant === null ) {
         $result->type = new Type();
         $result->type->structure = $this->builtinModule->typeStruct;
         $result->type->spec = TYPESPEC_STRUCT_TYPE;
         $result->inhabitant = $result->type;
      }

      return $result;
   }
}
