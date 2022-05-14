<?php

declare( strict_types = 1 );

class Assembler {
   public function __construct() {

   }

   public function assemble( string $source, string $object ): void {
      $command = sprintf( 'yasm -f elf64 "%s" -o "%s"', $source, $object );
      printf( "%s\n", $command );
      if ( system( $command ) === false ) {
         printf( "error: failed to execute assembler\n" );
         exit( 1 );
      }
   }
}
