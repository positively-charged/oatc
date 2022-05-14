<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

class StackFrame {
   /** @var CVar[] */
   public array $vars;
   /** @var CVar[] */
   public array $freeVars;

   public function __construct() {
      $this->vars = [];
      $this->freeVars = [];
   }

   public function allocVar(): CVar {
      foreach ( $this->vars as $var ) {
         if ( ! $var->allocated ) {
            $var->allocated = true;
            $var->refs = 1;
            return $var;
         }
      }
      $var = new CVar();
      $var->name = sprintf( 'v%d', count( $this->vars ) + 1 );
      $var->allocated = true;
      $var->refs = 1;
      $this->vars[] = $var;
      return $var;
   }

   public function findVarByLabel( string $label ): ?CVar {
      foreach ( $this->vars as $var ) {
         if ( $var->label === $label ) {
            return $var;
         }
      }
      return null;
   }

   public function release( CVar $var ): void {
      --$var->refs;
      if ( $var->refs === 0 ) {
         //$var->allocated = false;
         //var_dump( 'freeing: ' . $var->name . ' ' . $var->label );
      }
   }
}
