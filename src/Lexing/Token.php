<?php

declare( strict_types = 1 );

namespace Lexing;

$value = 0;
define( 'TK_END', $value++ );
define( 'TK_PUB', $value++ );
define( 'TK_FUN', $value++ );
define( 'TK_ID', $value++ );
define( 'TK_LPAREN', $value++ );
define( 'TK_RPAREN', $value++ );
define( 'TK_RBRACE', $value++ );
define( 'TK_LBRACE', $value++ );
define( 'TK_BAR', $value++ );
define( 'TK_EQ', $value++ );
define( 'TK_EQEQ', $value++ );
define( 'TK_COMMA', $value++ );
define( 'TK_INTEGER_LITERAL', $value++ );
define( 'TK_STRING_LITERAL', $value++ );
define( 'TK_SEMICOLON', $value++ );
define( 'TK_COLON', $value++ );
define( 'TK_INT', $value++ );
define( 'TK_ENUM', $value++ );
define( 'TK_IF', $value++ );
define( 'TK_ELIF', $value++ );
define( 'TK_ELSE', $value++ );
define( 'TK_SWITCH', $value++ );
define( 'TK_MATCH', $value++ );
define( 'TK_CASE', $value++ );
define( 'TK_DEFAULT', $value++ );
define( 'TK_WHILE', $value++ );
define( 'TK_FOR', $value++ );
define( 'TK_IN', $value++ );
define( 'TK_BREAK', $value++ );
define( 'TK_CONTINUE', $value++ );
define( 'TK_GOTO', $value++ );
define( 'TK_TRUE', $value++ );
define( 'TK_FALSE', $value++ );
define( 'TK_BANG', $value++ );
define( 'TK_BANG_EQ', $value++ );
define( 'TK_SLASH', $value++ );
define( 'TK_SLASHCOLONCOLON', $value++ );
define( 'TK_BITAND', $value++ );
define( 'TK_LOGAND', $value++ );
define( 'TK_LT', $value++ );
define( 'TK_LTEQ', $value++ );
define( 'TK_GT', $value++ );
define( 'TK_GTEQ', $value++ );
define( 'TK_LBRAC', $value++ );
define( 'TK_LBRAC2', $value++ );
define( 'TK_RBRAC', $value++ );
define( 'TK_RBRAC2', $value++ );
define( 'TK_DOT', $value++ );
define( 'TK_PLUS', $value++ );
define( 'TK_PLUS_PLUS', $value++ );
define( 'TK_MINUS', $value++ );
define( 'TK_MINUS_MINUS', $value++ );
define( 'TK_AT', $value++ );
define( 'TK_COMPILETIME', $value++ );
define( 'TK_RETURN', $value++ );
define( 'TK_MUT', $value++ );
define( 'TK_IMMUT', $value++ );
define( 'TK_TRAIT', $value++ );
define( 'TK_ARROW', $value++ );
define( 'TK_IMPLEMENTS', $value++ );
define( 'TK_COLONCOLON', $value++ );
define( 'TK_EQLT', $value++ );
define( 'TK_IMPORT', $value++ );
define( 'TK_CONST', $value++ );
define( 'TK_STAR', $value++ );
define( 'TK_MOD', $value++ );
define( 'TK_MODKW', $value++ );
define( 'TK_VIRT', $value++ );
define( 'TK_AND', $value++ );
define( 'TK_ON', $value++ );
define( 'TK_OR', $value++ );
define( 'TK_NOT', $value++ );
define( 'TK_NEW', $value++ );
define( 'TK_PTR', $value++ );
define( 'TK_ARRAY', $value++ );
define( 'TK_SIZEOF', $value++ );
define( 'TK_NULL', $value++ );
define( 'TK_LET', $value++ );
define( 'TK_NL', $value++ );
define( 'TK_DO', $value++ );
define( 'TK_ENDKW', $value++ );
define( 'TK_ENDFULLY', $value++ );
define( 'TK_TIDLE', $value++ );
define( 'TK_TIDLE_TILDE', $value++ );
define( 'TK_STRUCT', $value++ );
define( 'TK_BANG_TIDLE', $value++ );
define( 'TK_QUESTION_MARK', $value++ );
define( 'TK_DROP', $value++ );
define( 'TK_GEN', $value++ );
define( 'TK_REB', $value++ );
define( 'TK_UNDERSCORE', $value++ );
define( 'TK_VARIAN', $value++ );
unset( $value );

class Token {
   public int $type;
   public int $start;
   public int $length;
   public int $resumePos;
   public Position $pos;
}
