<?php

declare( strict_types = 1 );

namespace Codegen\C;

const CCALL_OAT_USER = -1;
const CCALL_OAT_INT_INIT = 0;
const CCALL_OAT_INT_ADD = 1;
const CCALL_OAT_INT_EQ = 2;
const CCALL_OAT_INT_PRINT = 3;
const CCALL_OAT_BOOL_INIT = 100;
const CCALL_OAT_BOOL_PRINT = 101;
const CCALL_OAT_STR_INIT = 200;

const CARG_LITERAL = 0;
const CARG_VAR = 1;

class CArg {
   public int $type;
   public $value;
   public CVar $var;
}

/**
 * Call to initialization function.
 */
class CInit {
   public int $func;
   public CVar $object;
   public array $initializers;

   public function __construct( int $func ) {
      $this->func = $func;
      $this->initializers = [];
   }
}

class CCall {
   public int $func;
   public array $args;
   public CFunc $userFunc;

   public function __construct( int $func ) {
      $this->func = $func;
      $this->args = [];
   }

   public function addArg( CVar $arg ): void {
      $this->add( CARG_VAR, $arg );
   }

   public function addLiteralArg( CLiteral $arg ): void {
      $this->add( CARG_LITERAL, $arg );
   }

   private function add( int $type, $value ): void {
      $arg = new CArg();
      $arg->type = $type;
      $arg->value = $value;
      if ( $type == CARG_VAR ) {
         $arg->var = $value;
      }
      array_push( $this->args, $arg );
   }

   public function output( CContent $content ): void {
      switch ( $this->func ) {
      case CCALL_OAT_INT_INIT:
         $content->append( "OatInt_Init();\n" );
         break;
      case CCALL_OAT_INT_EQ:
         $content->append( "OatInt_Eq();\n" );
         break;
      case CCALL_OAT_STR_INIT:
         $content->append( "OatStr_Init();\n" );
         break;
      }
   }
}
