<?php

declare( strict_types = 1 );

define( 'EXIT_SUCCESS', 0 );
define( 'EXIT_FAILURE', 1 );

function ASSERT_( bool $value ) {
   if ( ! $value ) {
      printf( "assertion failed\n" );
      throw new Exception();
   }
}

class Unreachable extends Exception {
   public function show(): void {
      $trace = $this->getTrace();
      if ( count( $trace ) > 0 ) {
         $call = array_shift( $trace );
         if ( $call[ 'function' ] == 'UNREACHABLE' ) {
            printf( "%s:%d: ", $call[ 'file' ], $call[ 'line' ] );
         }
      }
      printf( "fatal error: unreachable code" );
      if ( ( $msg = $this->getMessage() ) != '' ) {
         printf( ': %s', $msg );
      }
      printf( "\n" );
      printf( "%s\n", $this->getTraceAsString() );
   }
}

function UNREACHABLE( string $format = '', ...$args ) {
   throw new Unreachable( vsprintf( $format, $args ) );
}
