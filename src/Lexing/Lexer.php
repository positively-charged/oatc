<?php

declare( strict_types = 1 );

namespace Lexing;

use \User;

class Lexer {
   public Token $token;
   public int $tk;
   public int $tk_start;
   public int $tk_length;
   public int $tk_line;

   private User $user;
   private string $text;
   private int $pos;
   private int $line;
   private int $lineBeginPos;
   public string $file;

   public function __construct( User $user, string $file, string $text ) {
      $this->tk = TK_END;
      $this->tk_start = 0;
      $this->tk_length = 0;
      $this->user = $user;
      $this->text = $text . "\0";
      $this->pos = 0;
      $this->line = 1;
      $this->lineBeginPos = 0;
      $this->file = $file;
   }

   public function peek(): int {
      $token = $this->readToken( $this->pos );
      return $token->type;
   }

   public function read(): Token {
      $token = $this->readToken( $this->pos );
      $this->token = $token;
      $this->tk = $token->type;
      $this->tk_start = $token->start;
      $this->tk_length = $token->length;
      $this->tk_line = $token->pos->line;
      $this->pos = $token->resumePos;
      return $token;
   }

   private function readToken( int $pos ): Token {
      $tk = TK_END;
      $end_pos = -1;

      whitespace:
      // --------------------------------------------------------------------
      while ( ctype_space( $this->text[ $pos ] ) ) {
         if ( $this->text[ $pos ] == "\n" ) {
            ++$this->line;
            $this->lineBeginPos = $pos + 1;
         }
         ++$pos;
      }

      $start_pos = $pos;
      if ( ctype_alpha( $this->text[ $pos ] ) ) {
         goto identifier;
      }
      else if ( $this->text[ $pos ] === '_' ) {
         ++$pos;
         if ( ctype_alpha( $this->text[ $pos ] ) ) {
            goto identifier;
         }
         else {
            $tk = TK_UNDERSCORE;
            goto finish;
         }
      }
      else if ( ctype_digit( $this->text[ $pos ] ) ) {
         goto number;
      }
      else {
         switch ( $this->text[ $pos ] ) {
         case '(':
            ++$pos;
            $tk = TK_LPAREN;
            goto finish;
         case ')':
            ++$pos;
            $tk = TK_RPAREN;
            goto finish;
         case '{':
            ++$pos;
            //$this->enter( CONTEXT_PAREN );
            $tk = TK_LBRACE;
            goto finish;
         case '}':
            ++$pos;
            //$this->leave( CONTEXT_PAREN );
            $tk = TK_RBRACE;
            goto finish;
         case ',':
            ++$pos;
            $tk = TK_COMMA;
            goto finish;
         case ';':
            ++$pos;
            $tk = TK_SEMICOLON;
            goto finish;
         case '"':
            ++$pos;
            goto str;
         case '|':
            ++$pos;
            $tk = TK_BAR;
            goto finish;
         case '*':
            ++$pos;
            $tk = TK_STAR;
            goto finish;
         case '%':
            ++$pos;
            $tk = TK_MOD;
            goto finish;
         case '=':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case '=':
               ++$pos;
               $tk = TK_EQEQ;
               goto finish;
            case '>':
               ++$pos;
               $tk = TK_EQLT;
               goto finish;
            default:
               $tk = TK_EQ;
            }
            goto finish;
         case '!':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case '=':
               ++$pos;
               $tk = TK_BANG_EQ;
               goto finish;
            case '~':
               ++$pos;
               $tk = TK_BANG_TIDLE;
               goto finish;
            default:
               $tk = TK_BANG;
            }
            goto finish;
         case ':':
            ++$pos;
            if ( $this->text[ $pos ] == ':' ) {
               ++$pos;
               $tk = TK_COLONCOLON;
               goto finish;
            }
            $tk = TK_COLON;
            goto finish;
         case '/':
            ++$pos;
            $tk = TK_SLASH;
            /*
            if ( $this->text[ $pos ] == '/' ) {
               ++$pos;
               goto comment;
            }
            else if ( $this->text[ $pos ] == '*' ) {
               ++$pos;
               goto block_comment;
            }
            */
            goto finish;
         case '#':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case '[':
               ++$pos;
               goto block_comment;
            case ']':
               $this->user->diag( DIAG_SYNTAX | DIAG_ERR,
                  $this->createPos( $pos - 1 ),
                  'closing tag of multi-line comment but no comment is open' );
               $this->user->bail();
               break;
            }
            goto comment;
         case '&':
            ++$pos;
            if ( $this->text[ $pos ] == '&' ) {
               ++$pos;
               $tk = TK_LOGAND;
               goto finish;
            }
            $tk = TK_BITAND;
            goto finish;
         case '<':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case '=':
               ++$pos;
               $tk = TK_LTEQ;
               break;
            default:
               $tk = TK_LT;
            }
            goto finish;
         case '>':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case '=':
               ++$pos;
               $tk = TK_GTEQ;
               break;
            default:
               $tk = TK_GT;
            }
            goto finish;
         case '.':
            ++$pos;
            $tk = TK_DOT;
            goto finish;
         case '[':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case '[':
               ++$pos;
               $tk = TK_LBRAC2;
               break;
            default:
               $tk = TK_LBRAC;
            }
            goto finish;
         case ']':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case ']':
               ++$pos;
               $tk = TK_RBRAC2;
               break;
            default:
               $tk = TK_RBRAC;
            }
            goto finish;
         case '+':
            ++$pos;
            if ( $this->text[ $pos ] == '+' ) {
               $tk = TK_PLUS_PLUS;
               ++$pos;
               goto finish;
            }
            $tk = TK_PLUS;
            goto finish;
         case '-':
            ++$pos;
            if ( $this->text[ $pos ] == '>' ) {
               $tk = TK_ARROW;
               ++$pos;
               goto finish;
            }
            else if ( $this->text[ $pos ] == '-' ) {
               $tk = TK_MINUS_MINUS;
               ++$pos;
               goto finish;
            }
            $tk = TK_MINUS;
            goto finish;
         case '@':
            ++$pos;
            $tk = TK_AT;
            goto finish;
         case '~':
            ++$pos;
            switch ( $this->text[ $pos ] ) {
            case '~':
               $tk = TK_TIDLE_TILDE;
               ++$pos;
               break;
            default:
               $tk = TK_TIDLE;
               break;
            }
            goto finish;
         case '?':
            ++$pos;
            $tk = TK_QUESTION_MARK;
            goto finish;
         case "\0":
            goto finish;
         default:
            $this->user->diag( DIAG_SYNTAX | DIAG_ERR,
               $this->createPos( $pos ),
               'invalid character: %s', $this->text[ $pos ] );
            $this->user->bail();
         }
      }

      identifier:
      // --------------------------------------------------------------------
      while ( ctype_alnum( $this->text[ $pos ] ) ||
         $this->text[ $pos ] == '_' ) {
         ++$pos;
      }
      $tk = TK_ID;
      goto finish;

      number:
      // --------------------------------------------------------------------
      while ( ctype_digit( $this->text[ $pos ] ) ) {
         ++$pos;
      }
      $tk = TK_INTEGER_LITERAL;
      goto finish;

      str:
      // --------------------------------------------------------------------
      $start_pos = $pos;
      while ( $this->text[ $pos ] != '"' ) {
         ++$pos;
      }
      $tk = TK_STRING_LITERAL;
      $end_pos = $pos;
      ++$pos;
      goto finish;

      comment:
      // --------------------------------------------------------------------
      while ( $this->text[ $pos ] != "\0" && $this->text[ $pos ] != "\n" ) {
         ++$pos;
      }
      goto whitespace;

      block_comment:
      // --------------------------------------------------------------------
      $openTagCol = $start_pos - $this->lineBeginPos + 1;
      $openTagLine = $this->line;
      $depth = 1;
      while ( $this->text[ $pos ] != "\0" ) {
         // Skip to newline.
         while ( $this->text[ $pos ] != "\n" ) {
            ++$pos;
         }
         ++$this->line;
         $this->lineBeginPos = $pos + 1;
         ++$pos;
         if ( $this->text[ $pos ] == "\0" ) {
            break;
         }

         // Find first non-space character.
         while ( $this->text[ $pos ] == ' ' ||
            $this->text[ $pos ] == "\t" ) {
            ++$pos;
         }

         // Non-whitespace characters must appear at or after the comment open
         // and close tags.
         if ( ! ctype_space( $this->text[ $pos ] ) ) {
            $col = $pos - $this->lineBeginPos + 1;
            if ( $col < $openTagCol ) {
               if ( $this->text[ $pos ] == '#' &&
                  $this->text[ $pos + 1 ] == ']' ) {
                  $this->user->diag( DIAG_SYNTAX | DIAG_ERR,
                     $this->createPos( $pos ),
                     'closing tag of block comment must align on the same ' .
                     'column as the opening tag' );
               }
               else {
                  $this->user->diag( DIAG_SYNTAX | DIAG_ERR,
                     $this->createPos( $pos ),
                     'text in a block comment must not appear before the open ' .
                     'and close tags' );
               }
               $pos = new Position();
               $pos->file = $this->file;
               $pos->line = $openTagLine;
               $pos->column = $openTagCol;
               $this->user->diag( DIAG_NONE, $pos,
                  'block comment starts here' );
               $this->user->bail();
            }

            if ( $this->text[ $pos ] == '#' &&
               $this->text[ $pos + 1 ] == '[' ) {
               $nestedOpenTagCol = $pos - $this->lineBeginPos + 1;
               if ( $nestedOpenTagCol == $openTagCol ) {
                  ++$depth;
               }
               $pos += 1;
            }
            else if ( $this->text[ $pos ] == '#' &&
               $this->text[ $pos + 1 ] == ']' ) {
               $closeTagCol = $pos - $this->lineBeginPos + 1;
               if ( $openTagCol == $closeTagCol ) {
                  --$depth;
                  //$pos += 2;
                  if ( $depth == 0 ) {
                     goto comment;
                  }
               }
               $pos += 1;
            }
            /*
            else if ( $this->text[ $pos ] == "\n" ) {
               ++$this->line;
               $this->lineBeginPos = $pos + 1;
            } */
            ++$pos;
         }
      }
      $pos = new Position();
      $pos->file = $this->file;
      $pos->line = $openTagLine;
      $pos->column = $openTagCol;
      $this->user->diag( DIAG_SYNTAX | DIAG_ERR, $pos,
         'unterminated multi-line comment' );
      $this->user->bail();
      goto whitespace;

      multiline_comment_end:
      // --------------------------------------------------------------------
      while ( $this->text[ $pos ] == ' ' ||
         $this->text[ $pos ] == "\t" ) {
         ++$pos;
      }
      if ( $this->text[ $pos ] != "\n" ) {
         $this->user->diag( DIAG_SYNTAX | DIAG_ERR,
            $this->createPos( $pos ),
            'characters must not appear after the closing tag of a comment' );
         $this->user->bail();
      }
      goto whitespace;

      finish:
      // --------------------------------------------------------------------
      if ( $end_pos == -1 ) {
         $end_pos = $pos;
      }
      $token = new Token();
      $token->type = $tk;
      $token->start = $start_pos;
      $token->length = $end_pos - $start_pos;
      $token->resumePos = $pos;
      $token->pos = new Position();
      $token->pos->line = $this->line;
      $token->pos->column = $start_pos - $this->lineBeginPos + 1;
      $token->pos->file = $this->file;
      return $token;
   }

   private function createPos( int $offset ): Position {
      $pos = new Position();
      $pos->file = $this->file;
      $pos->line = $this->line;
      $pos->column = $offset - $this->lineBeginPos + 1;
      return $pos;
   }

   public function copyTokenText(): string {
      return substr( $this->text, $this->tk_start, $this->tk_length );
   }

   public function copyText( Token $token ): string {
      return substr( $this->text, $token->start, $token->length );
   }

   /**
    * Debug method. This method goes through all the available tokens in the
    * token stream and outputs information about each token.
    */
   public function dumpTokenStream( User $user ): void {
      $done = false;
      while ( ! $done ) {
         $token = $this->read();
         switch ( $token->type ) {
         case TK_END:
            $done = true;
            break;
         default:
            $this->dumpToken( $user, $token );
         }
      }
   }

   public function dumpToken( User $user, Token $token ): void {
      $text = $this->copyText( $token );
      switch ( $token->type ) {
      case TK_STRING_LITERAL:
         $text = sprintf( '"%s"', $text );
         break;
      default:
         break;
      }
      $user->diag( DIAG_NONE, $token->pos,
         "type=%d text=`%s`", $token->type, $text );
   }
}
