<?php

declare( strict_types = 1 );

namespace Parse;

class TypeExprParser extends Parser {
   private \Module $module;

   public function __construct( private \Task $task, \User $user,
      \Lexing\Lexer $lexer, \Lexing\ScopeLexer $scopeLexer, \Module $module ) {
      parent::__construct( $user, $lexer, $scopeLexer );
      $this->module = $module;
   }

   public function readTypeExpr(): \TypeExpr {
      $expr = new \TypeExpr();
      $expr->pos = $this->scopeLexer->token->pos;
      $expr->root = $this->readVariant();
      return $expr;
   }

   private function readVariant(): \TypeVariant {
      $options = [];

      while ( true ) {
         $options[] = $this->readPrefix();
         if ( $this->scopeLexer->tk === TK_BAR ) {
            $this->readTk();
         }
         else {
            break;
         }
      }

      $variant = new \TypeVariant();
      $variant->options = $options;

      return $variant;
   }

   private function readPrefix(): \Node {
      if ( $this->scopeLexer->tk === TK_BITAND ) {
         return $this->readBorrow();
      }
      else if ( $this->scopeLexer->tk === TK_MINUS ) {
         $unary = new \Unary();
         $unary->pos = $this->scopeLexer->token->pos;
         $unary->op = UOP_MINUS;
         $this->readTk();
         $unary->operand = $this->readSuffix();
         return $unary;
      }
      else if ( $this->scopeLexer->tk === TK_PLUS ) {
         $unary = new \Unary();
         $unary->pos = $this->scopeLexer->token->pos;
         $unary->op = UOP_PLUS;
         $this->readTk();
         $unary->operand = $this->readSuffix();
         return $unary;
      }
      else if ( $this->scopeLexer->tk === TK_BANG ) {
         $unary = new \Unary();
         $unary->pos = $this->scopeLexer->token->pos;
         $unary->op = UOP_IMPORTANT;
         $this->readTk();
         $unary->operand = $this->readSuffix();
         return $unary;
      }
      else {
         return $this->readSuffix();
      }
   }

   private function readBorrow(): \Borrow {
      $this->testTk( TK_BITAND );
      $this->readTk();
      $mutable = false;
      if ( $this->scopeLexer->tkPotentialKw() === TK_MUT ) {
         $mutable = true;
         $this->readTk();
      }
      $borrow = new \Borrow();
      $borrow->mutable = $mutable;
      $borrow->operand = $this->readSuffix();
      return $borrow;
   }

   private function readSuffix(): \Node {
      $operand = $this->readPrimary();
      while ( true ) {
         switch ( $this->scopeLexer->tk ) {
         case TK_LBRAC:
            $operand = $this->readGenericCall( $operand );
            break;
         case TK_LPAREN:
            $operand = $this->readCall( $operand );
            break;
         case TK_DOT:
            $operand = $this->readAccess( $operand );
            break;
         default:
            return $operand;
         }
      }
   }

   private function readGenericCall( \Node $operand ): \TypeCall {
      $this->testTk( TK_LBRAC );
      $call = new \TypeCall();
      $call->pos = $this->scopeLexer->token->pos;
      $call->operand = $operand;
      $call->generic = true;
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RBRAC ) {
         $call->args = $this->readTypeArgumentList();
      }
      $this->testTk( TK_RBRAC );
      $this->readTk();
      return $call;
   }

   private function readCall( \Node $operand ): \TypeCall {
      $this->testTk( TK_LPAREN );
      $call = new \TypeCall();
      $call->pos = $this->scopeLexer->token->pos;
      $call->operand = $operand;
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RPAREN ) {
         $call->args = $this->readTypeArgumentList();
      }
      $this->testTk( TK_RPAREN );
      $this->readTk();
      return $call;
   }

   private function readAccess( \Node $operand ): \Access {
      $this->testTk( TK_DOT );
      $access = new \Access();
      $access->pos = $this->scopeLexer->token->pos;
      $this->readTk();
      $access->lside = $operand;
      $this->testTk( TK_ID );
      $access->memberName = $this->scopeLexer->copyTokenText();
      $this->readTk();
      return $access;
   }

   private function readPrimary(): \Node {
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_ID:
         return $this->readNameUsage();
      case TK_LPAREN:
         return $this->readTypeTuple();
      case TK_INTEGER_LITERAL:
         return $this->readIntegerLiteral();
      case TK_STRING_LITERAL:
         return $this->readStringLiteral();
      case TK_TRUE:
      case TK_FALSE:
         return $this->readBoolLiteral();
      case TK_STRUCT:
         return $this->readStructLiteral();
      default:
         $this->diag( DIAG_SYNTAX | DIAG_ERR, $this->scopeLexer->token->pos,
            "unexpected token: %d %s", $this->scopeLexer->tk,
            $this->scopeLexer->copyTokenText() );
         $this->bail();
      }
   }

   private function readNameUsage(): \NameUsage {
      $this->testTk( TK_ID );
      $usage = new \NameUsage();
      $usage->pos = $this->scopeLexer->token->pos;
      $usage->name = $this->scopeLexer->copyTokenText();
      $this->readTk();
      return $usage;
   }

   private function readTypeTuple(): \TypeTuple {
      $this->testTk( TK_LPAREN );
      $this->readTk();
      $args = [];
      if ( $this->scopeLexer->tk !== TK_RPAREN ) {
         $args = $this->readTypeArgumentList();
      }
      $this->testTk( TK_RPAREN );
      $this->readTk();
      $tuple = new \TypeTuple();
      $tuple->args = $args;
      return $tuple;
   }

   /**
    * @return \TypeArg[]
    */
   public function readTypeArgumentList(): array {
      $args = [];

      while ( true ) {
         $name = '';
         if ( ( $this->scopeLexer->tk === TK_ID &&
            $this->scopeLexer->peek() === TK_COLON ) ) {
            $name = $this->scopeLexer->copyTokenText();
            $this->readTk();
            $this->testTk( TK_COLON );
            $this->readTk();
         }

         $expr = $this->readTypeExpr();

         $arg = new \TypeArg();
         $arg->name = $name;
         $arg->expr = $expr;
         $args[] = $arg;

         if ( $this->scopeLexer->tk === TK_COMMA ) {
            $this->readTk();
            if ( $this->scopeLexer->tk === TK_RPAREN ) {
               break;
            }
         }
         else {
            break;
         }
      }

      return $args;
   }

   private function readIntegerLiteral(): \IntegerLiteral {
      $this->testTk( TK_INTEGER_LITERAL );
      $literal = new \IntegerLiteral();
      $literal->pos = $this->scopeLexer->token->pos;
      $literal->value = ( int ) $this->scopeLexer->copyTokenText();
      $this->readTk();
      return $literal;
   }

   private function readStringLiteral(): \StringLiteral {
      $this->testTk( TK_STRING_LITERAL );
      $literal = new \StringLiteral();
      $literal->value = $this->scopeLexer->copyTokenText();
      $literal->index = $this->task->internString( $literal->value );
      $this->readTk();
      return $literal;
   }

   private function readBoolLiteral(): \BoolLiteral {
      $value = 0;
      if ( $this->scopeLexer->tkPotentialKw() === TK_TRUE ) {
         $this->readTk();
         $value = 1;
      }
      else {
         $this->testKw( TK_FALSE );
         $this->readTk();
      }
      $literal = new \BoolLiteral();
      $literal->value = $value;
      return $literal;
   }

   private function readStructLiteral(): \Node {
      $declParser = new DeclParser( $this->task, $this->user, $this->lexer,
         $this->scopeLexer, $this->module );
      return $declParser->readStruct( [], false );
   }
}
