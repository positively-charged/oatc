<?php

declare( strict_types = 1 );

use Parse\Position;

const DIAG_NONE = 0b0;
const DIAG_ERR = 0b1;
const DIAG_WARN = 0b10;
const DIAG_NOTICE = 0b100;
const DIAG_SYNTAX = 0b10000;
const DIAG_DEFERRED = 0b100000;
const DIAG_INTERNAL = 0b1000000;

class Diagnostic {
   public string $message;
}

class User {
   private int $argc;
   private array $argv;
   public bool $showErrsAsWarnings;
   public bool $errsReported;

   public function __construct( int $argc, array $argv,
      bool $showErrsAsWarnings = false ) {
      $this->argc = $argc;
      $this->argv = $argv;
      $this->showErrsAsWarnings = $showErrsAsWarnings;
      $this->errsReported = false;
   }

   public function diag( int $flags, ?\Lexing\Position $pos, string $format,
      ... $args ): Diagnostic {
      $diag = $this->err( $flags, $pos, $format, ... $args );

      if ( ( $flags & DIAG_ERR ) !== 0 && $this->showErrsAsWarnings ) {
         $warning = $this->err( $flags | DIAG_DEFERRED, $pos, $format,
            ... $args );
         printf( "%s\n", $warning->message );
      }
      else {
         printf( "%s\n", $diag->message );
      }

      return $diag;
   }

   public function err( int $flags, ?\Lexing\Position $pos, string $format,
      ... $args ): Diagnostic {
      $msg = '';
      if ( ! is_null( $pos ) ) {
         $msg = sprintf( '%s:%d:%d: ', $pos->file, $pos->line, $pos->column );
      }

      if ( $flags & DIAG_ERR ) {
         if ( $flags & DIAG_DEFERRED ) {
            $msg .= 'deferred ';
         }
         else if ( $flags & DIAG_INTERNAL ) {
            $msg .= 'internal ';
         }
         if ( $flags & DIAG_SYNTAX ) {
            $msg .= 'syntax ';
         }
         $msg .= 'error: ';
         $this->errsReported = true;
      }
      else if ( $flags & DIAG_WARN ) {
         $msg .= 'warning: ';
      }
      else if ( $flags & DIAG_NOTICE ) {
         $msg .= 'notice: ';
      }
      $msg .= vsprintf( $format, $args );
      $diag = new Diagnostic();
      $diag->message = $msg;
      return $diag;
   }

   public function isErrorsReported(): bool {
      return $this->errsReported;
   }

   /**
    * @throws
    */
   public function bail(): never {
      exit( EXIT_FAILURE );
   }
}
