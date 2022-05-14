<?php

declare( strict_types = 1 );

namespace Codegen\Php;

class Content {
   public string $output;
   private int $depth;

   public function __construct() {
      $this->output = '';
      $this->depth = 0;
   }

   public function writeInclude( string $path ): void {
      $this->append( "#include <%s>\n", $path );
   }

   public function writeUserInclude( string $path ): void {
      $this->append( "#include \"%s\"\n", $path );
   }

   public function newline(): void {
      $this->append( "\n" );
   }

   public function comment( string $text ): void {
      $this->append( "// %s\n", $text );
   }

   public function writePrototype( string $name ): void {
      $this->append( "void %s( void );\n", $name );
   }

   public function writeFuncStart( string $name ): void {
      $this->append( "void %s( void ) ", $name );
   }

   public function indent(): void {
      ++$this->depth;
   }

   public function dedent(): void {
      if ( $this->depth > 0 ) {
         --$this->depth;
      }
   }

   public function append( string $format, ...$args ) {
      if ( substr( $this->output, -1 ) == "\n" ) {
         $this->output .= str_repeat( '   ', $this->depth );
      }
      $this->output .= vsprintf( $format, $args );
   }

   public function write( string $format, ...$args ) {
      if ( substr( $this->output, -1 ) == "\n" ) {
         $this->output .= str_repeat( '   ', $this->depth );
      }
      $this->output .= vsprintf( $format, $args );
   }
}
