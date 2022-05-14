<?php

declare( strict_types = 1 );

namespace Lexing;

use \User;

class Scope {
   public array $names = [];
}

class ScopeLexer {
   public int $tk;
   public Token $token;

   public Lexer $lexer;
   /** @param Scope[] */
   private array $scopes;

   public function __construct( Lexer $lexer ) {
      $this->lexer = $lexer;
      $this->scopes = [];
      $this->tk = TK_END;
      $this->pushScope();
   }

   public function peek(): int {
      return $this->lexer->peek();
   }

   public function read(): Token {
      $this->token = $this->lexer->read();
      $this->tk = $this->token->type;
      return $this->token;
   }

   public function tkPotentialKw(): int {
      if ( $this->lexer->tk === TK_ID ) {
         return $this->determineIdTk();
      }
      return $this->lexer->tk;
   }

   public function determineIdTk(): int {
      // Keyword.
      $keywords = [
         'and' => TK_AND,
         'array' => TK_ARRAY,
         'break' => TK_BREAK,
         'case' => TK_CASE,
         'compiletime' => TK_COMPILETIME,
         'const' => TK_CONST,
         'continue' => TK_CONTINUE,
         'default' => TK_DEFAULT,
         'do' => TK_DO,
         'drop' => TK_DROP,
         'elif' => TK_ELIF,
         'else' => TK_ELSE,
         'end' => TK_ENDKW,
         'endfully' => TK_ENDFULLY,
         'enum' => TK_ENUM,
         'false' => TK_FALSE,
         'for' => TK_FOR,
         'fun' => TK_FUN,
         'gen' => TK_GEN,
         'goto' => TK_GOTO,
         'if' => TK_IF,
         'immut' => TK_IMMUT,
         'implements' => TK_IMPLEMENTS,
         'import' => TK_IMPORT,
         'in' => TK_IN,
         'let' => TK_LET,
         'match' => TK_MATCH,
         'mod' => TK_MODKW,
         'mut' => TK_MUT,
         'new' => TK_NEW,
         'not' => TK_NOT,
         'null' => TK_NULL,
         'on' => TK_ON,
         'or' => TK_OR,
         'ptr' => TK_PTR,
         'pub' => TK_PUB,
         'reb' => TK_REB,
         'return' => TK_RETURN,
         'sizeof' => TK_SIZEOF,
         'struct' => TK_STRUCT,
         'switch' => TK_SWITCH,
         'trait' => TK_TRAIT,
         'true' => TK_TRUE,
         'varian' => TK_VARIAN,
         'virt' => TK_VIRT,
         'while' => TK_WHILE,
      ];
      $realKws = [
         '_If' => TK_IF,
      ];
      $id = $this->lexer->copyTokenText();
      if ( array_key_exists( $id, $realKws ) ) {
         return $realKws[ $id ];
      }
      else {
         if ( array_key_exists( $id, $keywords ) ) {
            $tk = $this->findKw( $id );
            if ( $tk === TK_END ) {
               return $keywords[ $id ];
            }
         }
      }
      return TK_ID;
   }

   private function findKw( string $name ): int {
      $i = count( $this->scopes ) - 1;
      while ( $i >= 0 ) {
         if ( array_key_exists( $name, $this->scopes[ $i ]->names ) ) {
            return $this->scopes[ $i ]->names[ $name ];
         }
         --$i;
      }
      return TK_END;
   }

   public function pushScope(): void {
      $scope = new Scope();
      $this->scopes[] = $scope;
   }

   public function popScope(): void {
      array_pop( $this->scopes );
   }

   public function bindName( string $name ): void {
      $scope = end( $this->scopes );
      if ( $scope !== null ) {
         $scope->names[ $name ] = TK_ID;
      }
   }

   public function copyTokenText(): string {
      return $this->lexer->copyTokenText();
   }
}
