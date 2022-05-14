<?php

declare( strict_types = 1 );

namespace Codegen\C;

class CTranslationUnit {
   private array $headers;
   private array $userHeaders;
   private array $funcs;

   public function __construct() {
      $this->headers = [];
      $this->userHeaders = [];
      $this->funcs = [];
   }

   public function addHeader( string $path ): void {
      if ( ! in_array( $path, $this->headers ) ) {
         array_push( $this->headers, $path );
      }
   }

   public function addUserHeader( string $path ): void {
      if ( ! in_array( $path, $this->userHeaders ) ) {
         array_push( $this->userHeaders, $path );
      }
   }

   public function addFunc( CFunc $func ): void {
      array_push( $this->funcs, $func );
   }

   public function outputToFile( string $file ): void {
      file_put_contents( $file, $this->output() );
   }

   public function output(): string {
      $content = new CContent();
      $this->outputHeaders( $content );
      $this->outputUserHeaders( $content );
      $this->outputPrototypes( $content );
      $this->outputFuncs( $content );
      return $content->output;
   }

   private function outputHeaders( CContent $content ): void {
      foreach ( $this->headers as $path ) {
         $content->writeInclude( $path );
      }
      $content->newline();
   }

   private function outputUserHeaders( CContent $content ): void {
      foreach ( $this->userHeaders as $path ) {
         $content->writeUserInclude( $path );
      }
      $content->newline();
   }

   private function outputPrototypes( CContent $content ): void {
      if ( count( $this->funcs ) > 0 ) {
         $content->comment( "Prototypes" );
         foreach ( $this->funcs as $func ) {
            //$content->writePrototype( $func->name );
            $func->outputPrototype( $content );
         }
         $content->newline();
      }
   }

   private function outputFuncs( CContent $content ): void {
      if ( count( $this->funcs ) > 0 ) {
         $content->comment( "Functions" );
         foreach ( $this->funcs as $func ) {
            //$content->writeType( )
            $func->output( $content );
            $content->newline();
         }
         $content->newline();
      }
   }

   private function outputBlockStmt( CContent $content,
      CStmt $stmt ): void {
      $content->append( "{\n" );
      $content->indent();
      // Variables.
      foreach ( $stmt->vars as $var ) {
         //$this->outputVar( $content, $var );
         $var->output( $content );
      }

      // Statements.
      foreach ( $stmt->stmts as $stmt ) {
         $this->outputStmt( $content, $stmt );
      }
      $content->dedent();
      $content->append( "}\n" );
   }

   private function outputStmt( CContent $content,
      CStmt $stmt ): void {
      switch ( $stmt->type ) {
      case CSTMT_ECHO:
         $this->outputEchoStmt( $content, $stmt );
         break;
      case CSTMT_IF:
         $this->outputIfStmt( $content, $stmt );
         break;
      case CSTMT_EXPR:
         $this->outputExprStmt( $content, $stmt );
         break;
      }
   }

   private function outputEchoStmt( CContent $content,
      CStmt $stmt ): void {
      // Evaluate arguments.
      foreach ( $stmt->echoArgs as $arg ) {
         $this->outputExpr( $content, $arg );
      }

      // Print arguments.
      foreach ( $stmt->echoArgs as $arg ) {
         switch ( $arg->result->type ) {
         case RUNTIMEVALUE_INT:
            $content->append( 'OatInt_Print' );
            break;
         case RUNTIMEVALUE_BOOL:
            $content->append( 'OatBool_Print' );
            break;
         case RUNTIMEVALUE_STR:
            $content->append( 'OatStr_Print' );
            break;
         }
         $content->append( "( " );
         $content->append( "&%s", $arg->result->name() );
         $content->append( " )" );
         $content->append( ";\n" );
      }
   }

   private function outputIfStmt( CContent $content,
      CStmt $stmt ): void {
      $this->outputExpr( $content, $stmt->cond );
   }

   private function outputExprStmt( CContent $content,
      CStmt $stmt ): void {
      $this->outputExpr( $content, $stmt->expr );
   }

   private function outputExpr( CContent $content,
      CExpr $expr ): void {
      foreach ( $expr->calls as $call ) {
         $this->outputCall( $content, $call );
      }
      //$this->outputOperand( $content, $expr );
   }

   private function outputCall( CContent $content, CCall $call ): void {
      switch ( $call->func ) {
      case CCALL_OAT_INT_INIT:
         $content->append( "OatInt_Init" );
         break;
      case CCALL_OAT_INT_EQ:
         $content->append( "OatInt_Eq" );
         break;
      case CCALL_OAT_INT_ADD:
         $content->append( 'OatInt_Add' );
         break;
      case CCALL_OAT_BOOL_INIT:
         $content->append( "OatBool_Init" );
         break;
      case CCALL_OAT_STR_INIT:
         $content->append( "OatStr_Init" );
         break;
      case CCALL_OAT_USER:
         $content->append( "%s", $call->userFunc->name );
         break;
      }

      // Add arguments.
      $content->append( '(' );
      if ( count( $call->args ) > 0 ) {
         $content->append( ' ' );
         $added = false;
         foreach ( $call->args as $arg ) {
            if ( $added ) {
               $content->append( ', ' );
            }
            switch ( $arg->type ) {
            case CARG_VAR:
               $content->append( "&{$arg->var->name()}" );
               break;
            case CARG_LITERAL:
               switch ( $arg->value->type ) {
               case RUNTIMEVALUE_INT:
                  $content->append( "%d", $arg->value->value );
                  break;
               case RUNTIMEVALUE_BOOL:
                  $content->append( "%s", $arg->value->value ? "true" : "false" );
                  break;
               case RUNTIMEVALUE_STR:
                  $content->append( "\"%s\"", $arg->value->value );
                  break;
               }
               break;
            }
            $added = true;
         }
         $content->append( ' ' );
      }
      $content->append( ')' );
      $content->append( ";\n" );
   }

   private function outputOperand( CContent $content,
      COperand $operand ): void {
      switch ( $operand->op ) {
      case C_OP_ASSIGNMENT:
         $this->outputAssignment( $content, $operand );
         break;
      }
   }

   private function outputAssignment( CContent $content,
      COperand $operand ): void {
      //$this->
   }
}
