<?php

declare( strict_types = 1 );

namespace Parse;

use User;
use Lexing;

class Parser {
   protected User $user;
   protected Lexing\Lexer $lexer;
   protected Lexing\Token $token;
   protected Lexing\ScopeLexer $scopeLexer;
   protected int $tk;

   public function __construct( User $user, Lexing\Lexer $lexer,
      Lexing\ScopeLexer $scopeLexer ) {
      $this->user = $user;
      $this->lexer = $lexer;
      $this->scopeLexer = $scopeLexer;
      $this->tk = $lexer->tk;
   }

   protected function readName(): \Name {
      $this->testTk( TK_ID );
      $name = new \Name();
      $name->pos = $this->scopeLexer->token->pos;
      $name->value = $this->scopeLexer->copyTokenText();
      $this->readTk();
      return $name;
   }

   protected function testTk( int $expectedTk ) {
      if ( $this->scopeLexer->tk !== $expectedTk ) {
      var_dump( $this->scopeLexer->tk );
      var_dump( $expectedTk );
         $this->user->diag( DIAG_SYNTAX | DIAG_ERR,
            $this->scopeLexer->token->pos,
            "unexpected token: %s", $this->scopeLexer->copyTokenText() );
         $this->user->bail();
      }
   }

   protected function testKw( int $expectedKw ) {
      if ( $this->scopeLexer->tkPotentialKw() !== $expectedKw ) {
         $this->user->diag( DIAG_SYNTAX | DIAG_ERR,
            $this->scopeLexer->token->pos,
            "unexpected token: %s, expecting keyword",
               $this->scopeLexer->copyTokenText() );
         $this->user->bail();
      }
   }

   protected function readTk() {
      $this->token = $this->scopeLexer->read();
      $this->tk = $this->token->type;
   }

   protected function diag( int $flags, ?\Lexing\Position $pos, string $format,
      ... $args ): void {
      $this->user->diag( $flags, $pos, $format, ... $args );
   }

   protected function bail(): void {
      $this->user->bail();
   }
}
