<?php

declare( strict_types = 1 );

namespace Codegen\C;

class CLiteral {
   public int $type;
   public $value;
}

class CExpr {
   public array $calls;
   public ?CVar $result;

   public function __construct() {
      $this->calls = [];
      $this->result = null;
   }

   public function appendCall( CCall $call ): void {
      array_push( $this->calls, $call );
   }

   public function output( CContent $content ): void {
      foreach ( $this->calls as $call ) {
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
}
