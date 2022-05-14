<?php

declare( strict_types = 1 );

namespace Codegen\C;

require_once CODEGEN_DIR . '/C/Result.php';
require_once CODEGEN_DIR . '/C/CContent.php';
require_once CODEGEN_DIR . '/C/CVar.php';
require_once CODEGEN_DIR . '/C/CFunc.php';
require_once CODEGEN_DIR . '/C/CTranslationUnit.php';
require_once CODEGEN_DIR . '/C/COperand.php';
require_once CODEGEN_DIR . '/C/CCall.php';
require_once CODEGEN_DIR . '/C/CExpr.php';
require_once CODEGEN_DIR . '/C/CStmt.php';

use \Node;
use \Module;
use \Structure;
use \Type;
use \Func;
use \BlockStmt;
use \IfStmt;
use \EchoStmt;
use \ExprSection;
use \ExprStmt;
use \Expr;
use \Assignment;
use \NameUsage;
use \DollarNameUsage;
use \IntegerLiteral;
use \BoolLiteral;
use \StringLiteral;
use \Binary;
use \Call;

class CodeGenerator {
   private string $output;
   private Module $module;
   private CTranslationUnit $translationUnit;
   private CFunc $cFunc;
   private array $funcsToCfuncs;

   public function __construct( Module $module ) {
      $this->module = $module;
      $this->translationUnit = new CTranslationUnit();
      $this->funcsToCfuncs = [];
      $this->output = '';
   }

   public function publish( string $outputFile ): void {
      $this->publishHeaderFiles();
      $this->publishPrototypes();

      /*
      foreach ( $this->module->body as $item ) {
         switch ( $item->nodeType ) {
         case NODE_BUNDLE:
            $this->publishBundle( $item );
            break;
         }
      }*/

      $this->createCFuncs();
      foreach ( $this->module->funcs as $func ) {
         $this->publishFunc( $func );
      }

      $this->translationUnit->outputToFile( $outputFile );
   }

   private function publishHeaderFiles(): void {
      $this->translationUnit->addHeader( 'stdio.h' );
      $this->translationUnit->addHeader( 'stdlib.h' );
      $this->translationUnit->addUserHeader( 'runtime.h' );
   }

   private function publishPrototypes(): void {
      foreach ( $this->module->items as $item ) {
         $this->visitPrototype( $item );
      }
   }

   private function visitPrototype( Node $item ): void {
      switch ( $item->nodeType ) {
      case NODE_STRUCTURE:
         $this->publishBundlePrototype( $item );
         break;
      case NODE_TRAIT:
      case NODE_FUNC:
         break;
      default:
         $this->unreachable();
      }
   }

   private function publishBundlePrototype( Structure $bundle ): void {
      $this->publishBundleHeader( $bundle );
      $this->append( ";\n" );
   }

   private function publishBundle( Structure $bundle ): void {
      if ( ! $bundle->generic ) {
         $this->publishBundleHeader( $bundle );
         $this->append( ' {' . "\n", $bundle->name );
         foreach ( $bundle->members as $member ) {
            $this->publishType( $member->type );
            $this->append( ' %s;' . "\n", $member->name );
         }
         $this->append( '};' . "\n" );
      }
   }

   private function publishBundleHeader( Structure $bundle ): void {
      $this->append( 'struct %s', $bundle->name );
   }

   private function createCFuncs(): void {
      foreach ( $this->module->funcs as $func ) {
         $cFunc = new CFunc();
         $cFunc->name = $func->name;
         foreach ( $func->params as $param ) {
            switch ( $param->type->spec ) {
            case TYPESPEC_INT:
               $cParam = $cFunc->addParam( RUNTIMEVALUE_INT );
               $cParam->name = $param->name;
               break;
            }
         }
         $this->translationUnit->addFunc( $cFunc );
         $this->funcsToCfuncs[ $cFunc->name ] = $cFunc;
      }
   }

   private function publishFunc( Func $func ): void {
   /*
      if ( $func->compiletime ) {
         return;
      }
   */

      $cFunc = $this->funcsToCfuncs[ $func->name ];
      $this->cFunc = $cFunc;
      $this->publishFuncBlockStmt( $cFunc, $func );
   }

   private function publishFuncHeader( Func $func ): void {
      $this->publishFuncQuals( $func );
      $this->publishType( $func->returnType );
      $this->append( ' %s(', $func->name );
      if ( count( $func->params ) > 0 ) {
         $this->publishParamList( $func );
      }
      else {
         $this->append( ' void ' );
      }
      $this->append( ')' );
   }

   private function publishFuncQuals( Func $func ): void {
      if ( ! $func->visible ) {
         $this->append( 'static ' );
      }
   }

   private function publishParamList( Func $func ): void {
      $added = false;
      foreach ( $func->params as $param ) {
         if ( $added ) {
            $this->append( ', ' );
         }
         $this->publishType( $param->type );
         $this->append( ' ' );
         $this->append( '%s', $param->name );
         $added = true;
      }
   }

   private function publishVar( Variable $var ): void {
      $this->publishType( $var->type );
      $this->append( ' ' );
      $this->append( '%s', $var->name );
      $this->publishDims( $var->type );
      $this->append( ';' . "\n" );
   }

   private function publishType( Type $type ): void {
      switch ( $type->describe() ) {
      case Type::DESC_ARRAY_PTR:
         $this->append( 'struct array_ptr' );
         return;
      }

      switch ( $type->spec ) {
      case TYPESPEC_INT:
         $this->append( 'OatInt' );
         break;
      case TYPESPEC_BOOL:
         $this->append( 'int' );
         break;
      case TYPESPEC_STRUCT:
         $this->append( 'struct %s', $type->structure->name );
         break;
      default:
         throw new Exception();
      }
      if ( ! is_null( $type->pointer ) ) {
         $this->publishPointer( $type->pointer );
      }
   }

   private function publishPointer( Pointer $pointer ): void {
      if ( ! is_null( $pointer->next ) ) {
         $this->publishPointer( $pointer->next );
      }
      else {
         $this->append( '*' );
      }
   }

   private function publishDims( Type $type ): void {
      if ( count( $type->dims ) > 0 ) {
         $this->append( '[' );
         $added = false;
         foreach ( $type->dims as $dim ) {
            if ( $added ) {
               $this->append( '*' );
            }
            $this->append( '%d', $dim->length->value );
            $added = true;
         }
         $this->append( ']' );
      }
   }

   private function publishFuncBlockStmt( CFunc $cFunc, Func $func ): void {
      $cFunc->body = new CStmt;
      $cFunc->body->type = CSTMT_BLOCK;
      $this->publishBlock( $func->body, $cFunc->body );
   }

   private function publishBlockStmt( BlockStmt $stmt ): CStmt {
      $cStmt = new CStmt;
      $cStmt->type = CSTMT_BLOCK;
      return $cStmt;
   }

   private function publishBlock( BlockStmt $stmt, CStmt $cStmt ): void {
      foreach ( $stmt->stmts as $stmt ) {
         $childStmt = $this->publishStmt( $stmt );
         $cStmt->addStmt( $childStmt );
      }
   }

   private function publishStmt( Node $stmt ): CStmt {
      switch ( $stmt->nodeType ) {
      case NODE_VAR:
         $this->publishVar( $stmt );
         break;
      case NODE_IF:
         return $this->publishIfStmt( $stmt );
      case NODE_SWITCH:
         $this->publishSwitchStmt( $stmt );
         break;
      case NODE_WHILE:
         $this->publishWhileStmt( $stmt );
         break;
      case NODE_RETURN_STMT:
         $this->publishReturnStmt( $stmt );
         break;
      case NODE_EXPR_STMT:
         $this->publishExprStmt( $stmt );
         break;
         /*
      case NODE_GOTO:
         $this->publishGotoStmt( $stmt );
         break;
      case NODE_LABEL:
         $this->publishLabel( $stmt );
         break;
      case NODE_ECHO:
         return $this->publishEchoStmt( $stmt );
      case NODE_EXPR_SECTION:
         return $this->publishExprSection( $stmt );
      case NODE_COMPILETIME_STMT:
         $this->publishCompiletimeStmt( $stmt );
         break;
         */
      default:
         throw new \Exception( 'unhandled node: ' . $stmt->nodeType );
      }
   }

   private function publishIfStmt( IfStmt $stmt ): CStmt {
      if ( $stmt->compiletime ) {
         if ( $stmt->cond->value != 0 ) {
            $this->publishBlockStmt( $stmt->body );
         }
         else {
            if ( ! is_null( $stmt->elseBody ) ) {
               switch ( $stmt->elseBody->nodeType ) {
               case NODE_IF:
                  $this->publishIfStmt( $stmt->elseBody );
                  break;
               case NODE_BLOCKSTMT:
                  $this->publishBlockStmt( $stmt->elseBody );
                  break;
               default:
                  throw new Exception();
               }
            }
         }
      }
      else {
         $cStmt = new CStmt();
         $cStmt->type = CSTMT_IF;
         //$this->append( 'if ( ' );
         $cond = $this->publishExpr( $stmt->cond );
         $cStmt->cond = $cond;
         //$this->append( ' ) ' );
         $cStmt->body = $this->publishBlockStmt( $stmt->body );
         if ( ! is_null( $stmt->elseBody ) ) {
            $this->append( 'else ' );
            switch ( $stmt->elseBody->nodeType ) {
            case NODE_IF:
               $this->publishIfStmt( $stmt->elseBody );
               break;
            case NODE_BLOCKSTMT:
               $this->publishBlockStmt( $stmt->elseBody );
               break;
            default:
               throw new Exception();
            }
         }
         return $cStmt;
      }
   }

   private function publishCompiletimeStmt( CompiletimeStmt $stmt ): void {
      switch ( $stmt->stmt->nodeType ) {
      case NODE_IF:
         $this->publishIfStmt( $stmt->stmt );
         break;
      }
   }

   private function publishSwitchStmt( SwitchStmt $stmt ): void {
      $this->append( 'switch ( ' );
      $this->publishExpr( $stmt->cond );
      $this->append( ' ) {' . "\n" );
      foreach ( $stmt->cases as $case ) {
         foreach ( $case->values as $expr ) {
            $this->append( 'case %d:' . "\n", $expr->value );
         }
         if ( $case->isDefault ) {
            $this->append( 'default:' . "\n" );
         }
         $this->publishBlockStmt( $case->body );
         $this->append( 'break;' . "\n" );
      }
      $this->append( '}' . "\n" );
   }

   private function publishWhileStmt( WhileStmt $stmt ): void {
      $this->append( 'while ( ' );
      $this->publishExpr( $stmt->cond );
      $this->append( ' ) ' );
      $this->publishBlockStmt( $stmt->body );
   }

   private function publishReturnStmt( ReturnStmt $stmt ): void {
      $this->append( 'return' );
      if ( $stmt->value != null ) {
         $this->append( ' ' );
         $this->publishExpr( $stmt->value );
      }
      $this->append( ';' . "\n" );
   }

   private function publishGotoStmt( GotoStmt $stmt ): void {
      $this->append( 'goto %s;' . "\n", $stmt->label );
   }

   private function publishLabel( Label $label ): void {
      $this->append( '%s:' . "\n", $label->name );
   }

   private function publishEchoStmt( EchoStmt $stmt ): CStmt {
      $cStmt = new CStmt;
      $cStmt->type = CSTMT_ECHO;
      foreach ( $stmt->args as $arg ) {
         $result = $this->publishExpr( $arg );
         $cStmt->addEchoArg( $result );
      }
      return $cStmt;
      /*
      $this->append( 'printf(' );
      $format = '';
      foreach ( $stmt->args as $arg ) {
         switch ( $arg->type->spec ) {
         case TYPESPEC_INT:
            $format .= '%d';
            break;
         case TYPESPEC_STR:
            $format .= '%s';
            break;
         }
      }
      $this->append( '"%s", ', $format );
      $added = false;
      foreach ( $stmt->args as $arg ) {
         if ( $added ) {
            $this->append( ', ' );
         }
         $this->publishExpr( $arg );
         $added = true;
      }
      $this->append( ');' . "\n" );*/
   }

   private function publishExprSection( ExprSection $section ): CStmt {
      foreach ( $section->exprStmts as $stmt ) {
         return $this->publishExprStmt( $stmt );
      }
   }

   private function publishExprStmt( ExprStmt $stmt ): CStmt {
      $result = $this->publishExpr( $stmt->expr );
      $cStmt = new CStmt();
      $cStmt->type = CSTMT_EXPR;
      $cStmt->expr = $result;
      return $cStmt;
   }

   private function publishExpr( Expr $expr ): CExpr {
      $this->cExpr = new CExpr();
      $result = $this->publishExprRoot( $expr->root );
      $this->cExpr->result = $result->cVar;
      return $this->cExpr;
   }

   private function publishExprRoot( Node $node ): Result {
      switch ( $node->nodeType ) {
      case NODE_ASSIGNMENT:
         return $this->publishAssignment( $node );
      case NODE_BINARY:
         return $this->publishBinary( $node );
      case NODE_LOGICAL:
         $this->publishLogical( $node );
         break;
      default:
         return $this->publishPrefix( $node );
      }
      return new Result;
   }

   private function publishAssignment( Assignment $assignment ): Result {
      //$this->runtime->integer->add( $lside->runtime_value,  )
      $lside = $this->publishExprRoot( $assignment->lside );
      $rside = $this->publishExprRoot( $assignment->rside );


var_dump( $lside );
var_dump( $rside );
      /*
               // Assign value to C variable.
               $call = new CCall( $func );
               $call->addArg( $cVar );
               $call->addArg( $rside->cVar );
               $this->cExpr->appendCall( $call );
               $result = new Result();
               $result->cVar = $lside;
               return $result; */

      switch ( $rside->cVar->type ) {
      case RUNTIMEVALUE_INT:
         $func = CCALL_OAT_INT_INIT;
         break;
      case RUNTIMEVALUE_BOOL:
         $func = CCALL_OAT_BOOL_INIT;
         break;
      case RUNTIMEVALUE_STR:
         $func = CCALL_OAT_STR_INIT;
         break;
      }
/*
      $cCall = new CCall( $func );
      $cCall->addArg( $lside->cVar );
      $cCall->addArg( $rside->cVar );
      $this->cExpr->appendCall( $cCall );
*/
/*
      $cResult = new CVar();
      $cResult->type = RUNTIMEVALUE_INT;
      $cCall->addArg( $cResult );
*/

      $this->cFunc->setVar( $lside->binding, $rside->cVar );

      //$this->cInt->init( );
      //$rside->decrement();
      $result = new Result();
      $result->binding = $lside->binding;
      $result->cVar = $rside->cVar;

      //$result->operand = $rside->operand->assign( $lside->operand );
      //$result->operand = $rside->operand->assign( $lside->operand );
      return $result;
   }

   private function publishBinary( Binary $binary ): Result {
      if ( isset( $binary->traitFunc ) ) {
         $this->append( '%s( &(', $binary->traitFunc->name );
         $this->publishExprRoot( $binary->lside );
         $this->append( '), &(' );
         $this->publishExprRoot( $binary->rside );
         $this->append( ') )' );
      }
      else {
         $lside = $this->publishExprRoot( $binary->lside );
         $rside = $this->publishExprRoot( $binary->rside );
         $result = new Result;
         switch ( $binary->op ) {
         case Binary::OP_EQ:
            $call = new CCall( CCALL_OAT_INT_EQ );
            $call->addArg( $lside->cVar );
            $call->addArg( $rside->cVar );
            $eqResult = new CVar();
            $call->addArg( $eqResult );
            $result->cVar = $eqResult;
            $this->cExpr->appendCall( $call );

            //$this->append( ' == ' );
            break;
         case Binary::OP_NEQ:
            $this->append( ' != ' );
            break;
         case Binary::OP_LT:
            $this->append( ' < ' );
            break;
         case Binary::OP_ADD:
            $this->append( ' + ' );
            $call = new CCall( CCALL_OAT_INT_ADD );
            $call->addArg( $lside->cVar );
            $call->addArg( $rside->cVar );
            $eqResult = $this->cFunc->allocVar();
            $eqResult->type = RUNTIMEVALUE_INT;
            $call->addArg( $eqResult );
            $result->cVar = $eqResult;
            $this->cExpr->appendCall( $call );

            break;
         case Binary::OP_SUB:
            $this->append( ' - ' );
            break;
         default:
            throw new Exception();
         }

         return $result;
      }
   }

   private function publishLogical( Logical $logical ): void {
      $this->publishExprRoot( $logical->lside );
      switch ( $logical->operator ) {
      case Logical::OPERATOR_AND:
         $this->append( ' && ' );
         break;
      case Logical::OPERATOR_OR:
         $this->append( ' || ' );
         break;
      }
      $this->publishExprRoot( $logical->rside );
   }

   private function publishPrefix( Node $node ): Result {
      switch ( $node->nodeType ) {
      /*
      case NODE_ADDROF:
         $this->publishAddrOf( $node );
         break;
      */
      default:
         return $this->publishSuffix( $node );
      }
      return new Result;
   }

   private function publishAddrOf( AddrOf $addrof ): void {
      if ( $addrof->takeAddrOfArray ) {
         $this->append( '(struct array_ptr){ 0, ' );
         $this->publishPrefix( $addrof->operand );
         $this->append( '}' );
      }
      else {
         $this->append( '&' );
         $this->publishPrefix( $addrof->operand );
      }
   }

   private function publishSuffix( Node $node ): Result {
      switch ( $node->nodeType ) {
      case NODE_CALL:
         $this->publishCall( $node );
         break;
      case NODE_ACCESS:
         $this->publishAccess( $node );
         break;
         /*
      case NODE_DEREFERENCE:
         $this->publishDereference( $node );
         break;
         */
      case NODE_SUBSCRIPT:
         $this->publishSubscript( $node );
         break;
      default:
         return $this->publishPrimary( $node );
      }
      return new Result;
   }

   private function publishCall( Call $call ): void {
      switch ( $call->func->builtin ) {
      case Func::BUILTIN_ADD:
         $this->publishExpr( $call->args[ 0 ] );
         $this->append( ' + ' );
         $this->publishExpr( $call->args[ 1 ] );
         break;
      case Func::BUILTIN_ASSIGN:
         $this->publishExpr( $call->args[ 0 ] );
         $this->append( ' = ' );
         $this->publishExpr( $call->args[ 1 ] );
         break;
      default:
         if ( $call->func->compiletime ) {
            $this->append( '%d', $call->value );
         }
         else {
            $this->publishUserCall( $call );
         }
      }
   }

   private function publishUserCall( Call $call ): void {
      $operand = $this->publishExprRoot( $call->operand );

      $cCall = new CCall( CCALL_OAT_USER );
      $cCall->userFunc = $operand->cFunc;

      // Arguments.
      if ( count( $call->args ) > 0 ) {
         foreach ( $call->args as $arg ) {
            $result = $this->publishExprRoot( $arg->root );
            $cCall->addArg( $result->cVar );
         }
      }

      $this->cExpr->appendCall( $cCall );
   }

   private function publishAccess( Access $access ): void {
      $this->publishSuffix( $access->lside );
      $this->append( '.%s', $access->memberName );
   }

   private function publishDereference( Dereference $deref ): void {
      if ( $deref->derefArrayPtr ) {
         $this->append( '((int*)(' );
         $this->publishSuffix( $deref->operand );
         $this->append( '.data))' );
      }
      else {
         $this->append( '(*' );
         $this->publishSuffix( $deref->operand );
         $this->append( ')' );
      }
   }

   private function publishSubscript( Subscript $subscript ): void {
      $this->append( 'get_elem(' );
      $this->publishSuffix( $subscript->operand );
      $this->append( ',' );
      $this->append( '%d', $subscript->dims[ 0 ]->size * $subscript->dims[ 0 ]->length->value );
      $this->append( ',' );
      foreach ( $subscript->indexes as $i => $index ) {
         if ( $i + 1 < count( $subscript->dims ) ) {
            $this->append( '(' );
         }
         $this->publishExpr( $index );
         if ( $i + 1 < count( $subscript->dims ) ) {
            $this->append( '*%d)+', $subscript->dims[ $i ]->size );
         }
      }
      $this->append( ')' );

      /*
            $this->publishSuffix( $subscript->operand );
            $this->append( '[ ' );
            foreach ( $subscript->indexes as $i => $index ) {
               if ( $i + 1 < count( $subscript->dims ) ) {
                  $this->append( '(' );
               }
               $this->publishExpr( $index );
               if ( $i + 1 < count( $subscript->dims ) ) {
                  $this->append( '*%d)+', $subscript->dims[ $i ]->size );
               }
            }
            $this->append( ' ]' );
      */
   }

   private function publishPrimary( Node $node ): Result {
      switch ( $node->nodeType ) {
      case NODE_NAME_USAGE:
         return $this->publishNameUsage( $node );
         /*
      case NODE_DOLLAR_NAME_USAGE:
         return $this->publishDollarNameUsage( $node );
         */
      case NODE_INTEGER_LITERAL:
         return $this->publishIntegerLiteral( $node );
      case NODE_BOOL_LITERAL:
         return $this->publishBoolLiteral( $node );
      case NODE_STRING_LITERAL:
         return $this->publishStringLiteral( $node );
      case NODE_NULL_POINTER:
         $this->publishNullPointer();
         break;
      default:
         printf( "unhandled node: %d\n", $node->nodeType );
         throw new \Exception();
      }
      return new Result;
   }

   private function publishNameUsage( NameUsage $usage ): Result {
      if ( $usage->compiletime ) {
         $this->append( '%d', $usage->value );
      }
      else {
         switch ( $usage->object->nodeType ) {
         case NODE_VAR:
         case NODE_PARAM:
            break;
         case NODE_FUNC:
            $result = new Result();
            $result->cFunc = $this->funcsToCfuncs[ $usage->object->name ];
            return $result;
         }
      }
   }

   private function publishDollarNameUsage( DollarNameUsage $usage ): Result {
   /*
      $cVar = null;
      if ( $usage->initial ) {

         $this->publishType( $usage->binding->type );
         $this->append( ' ' );
         $this->append( '%s', $usage->name );
         $this->publishDims( $usage->binding->type );
         $this->append( ';' . "\n" );

         $cVar = $this->cFunc->allocVar();
         switch ( $usage->binding->type->spec ) {
         case TYPESPEC_INT:
            $cVar->type = RUNTIMEVALUE_INT;
            $func = CCALL_OAT_INT_INIT;
            break;
         case TYPESPEC_BOOL:
            $cVar->type = RUNTIMEVALUE_BOOL;
            $func = CCALL_OAT_BOOL_INIT;
            var_dump( $usage );
            break;
         case TYPESPEC_STR:
            $cVar->type = RUNTIMEVALUE_STR;
            $func = CCALL_OAT_STR_INIT;
            break;
         }
         $cVar->name = $usage->name;
      }
      else {
         $cVar = $this->cFunc->getVar( $usage->name );
      } */
      //$operand = new COperand;
      //$operand->type = $cVar->type;
      //$operand->value = $cVar;
      $result = new Result;
      //$result->operand = $operand;
      switch ( $usage->object->nodeType ) {
      case NODE_VAR:
         $result->cVar = $this->cFunc->getVar( $usage->name );
         $result->binding = $usage->name;
         break;
      case NODE_PARAM:
         $param = $this->cFunc->getParam( $usage->name );
         $result->cVar = $param->var;
         $result->binding = $usage->name;
         break;
      }
      return $result;
   }

   private function publishVarUsage( Variable $var ): CodegenResult {
      $result = new CodegenResult();
      $result->dims = $var->dims;
      $result->append( '%s', $var->name );
      return $result;
   }

   private function publishIntegerLiteral( IntegerLiteral $literal ): Result {
      $call = new CCall( CCALL_OAT_INT_INIT );

      $lside = $this->cFunc->allocVar();
      $lside->type = RUNTIMEVALUE_INT;
      $call->addArg( $lside );

      $rside = new CLiteral();
      $rside->type = RUNTIMEVALUE_INT;
      $rside->value = $literal->value;
      $call->addLiteralArg( $rside );

      $this->cExpr->appendCall( $call );
      /*
      $call->addLiteralArg( $literal->value );
      $operand = new COperand();
      $operand->value = $literal->value;
      $operand->type = RUNTIMEVALUE_INT;
      */
      $result = new Result();
      $result->cVar = $lside;
      return $result;
   }

   private function publishBoolLiteral( BoolLiteral $literal ): Result {
      $call = new CCall( CCALL_OAT_BOOL_INIT );

      $lside = $this->cFunc->allocVar();
      $lside->type = RUNTIMEVALUE_BOOL;
      $call->addArg( $lside );

      $rside = new CLiteral();
      $rside->type = RUNTIMEVALUE_BOOL;
      $rside->value = $literal->value;
      $call->addLiteralArg( $rside );

      $this->cExpr->appendCall( $call );
      /*
      $call->addLiteralArg( $literal->value );
      $operand = new COperand();
      $operand->value = $literal->value;
      $operand->type = RUNTIMEVALUE_INT;
      */
      $result = new Result();
      $result->cVar = $lside;
      return $result;
   }

   private function publishStringLiteral( StringLiteral $literal ): Result {
      $call = new CCall( CCALL_OAT_STR_INIT );
      $lside = $this->cFunc->allocVar();
      $lside->type = RUNTIMEVALUE_STR;
      $call->addArg( $lside );
      $rside = new CLiteral();
      $rside->type = RUNTIMEVALUE_STR;
      $rside->value = $literal->value;
      $call->addLiteralArg( $rside );
      $this->cExpr->appendCall( $call );
      $result = new Result();
      $result->cVar = $lside;
      return $result;
      //$this->append( '"%s"', $literal->value );
   }

   private function publishNullPointer(): void {
      $this->append( 'NULL' );
   }

   private function append( string $format, ...$args ) {
      $this->output .= vsprintf( $format, $args );
   }
}
