<?php

declare( strict_types = 1 );

namespace Codegen\C;

const CSTMT_EXPR = 0;
const CSTMT_BLOCK = 1;
const CSTMT_IF = 2;
const CSTMT_ECHO = 3;

class CStmt {
   public int $type;
   public CExpr $expr;
   public array $stmts;
   public array $echoArgs;
   public array $vars;

   public function __construct() {
      $this->stmts = [];
      $this->echoArgs = [];
      $this->vars = [];
   }

   public function addStmt( CStmt $stmt ): void {
      array_push( $this->stmts, $stmt );
   }

   public function addEchoArg( CExpr $arg ): void {
      array_push( $this->echoArgs, $arg );
   }

   public function output( CContent $content ): void {
      $content->append( "{\n" );
      $content->indent();
      $this->outputStmtList( $content );
      $content->dedent();
      $content->append( "}\n" );
   }

   public function outputStmtList( CContent $content ): void {
      // Statements.
      foreach ( $this->stmts as $stmt ) {
         $this->outputStmt( $content, $stmt );
      }
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
         $arg->output( $content );
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

   private function outputExprStmt( CContent $content,
      CStmt $stmt ): void {
      $stmt->expr->output( $content );
   }
}
