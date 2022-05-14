<?php

declare( strict_types = 1 );

namespace Codegen\C;

class CVar {
   public int $type;
   public int $index;
   public bool $param = false;

   public function output( CContent $content ): void {
      switch ( $this->type ) {
      case RUNTIMEVALUE_INT:
         $this->outputIntVar( $content );
         break;
      case RUNTIMEVALUE_BOOL:
         $this->outputBoolVar( $content );
         break;
      case RUNTIMEVALUE_STR:
         $this->outputStrVar( $content );
         break;
      }
   }

   private function outputIntVar( CContent $content ): void {
      $content->append( "OatInt {$this->name()};\n" );
   }

   private function outputBoolVar( CContent $content ): void {
      $content->append( "OatBool {$this->name()};\n" );
   }

   private function outputStrVar( CContent $content ): void {
      $content->append( "OatStr {$this->name()};\n" );
   }

   public function name(): string {
      return "var{$this->index}";
   }
}
