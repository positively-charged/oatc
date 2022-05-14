<?php

declare( strict_types = 1 );

namespace Codegen\C;

class CParam {
   public int $type;
   public int $index;
   public CVar $var;
   public string $name;
}

class CFunc {
   public string $name;
   public CStmt $body;
   public array $bindings;
   public array $params;
   public array $vars;

   public function __construct() {
      $this->bindings = [];
      $this->params = [];
      $this->vars = [];
   }

   public function allocVar(): CVar {
      $var = new Cvar();
      array_push( $this->vars, $var );
      return $var;
   }

   public function setVar( string $name, CVar $var ): void {
      $this->bindings[ $name ] = $var;
   }

   public function getVar( string $name ): ?CVar {
      if ( array_key_exists( $name, $this->bindings ) ) {
         return $this->bindings[ $name ];
      }
      return null;
   }

   public function allocateVarIndexes(): void {
      foreach ( $this->vars as $index => $var ) {
         $var->index = $index;
      }
   }

   public function addParam( int $type ): CParam {
      $param = new CParam();
      $param->type = $type;
      $param->index = count( $this->params ) + 1;
      $param->var = $this->allocVar();
      $param->var->type = $type;
      $param->var->param = true;
      array_push( $this->params, $param );
      return $param;
   }

   public function getParam( string $name ): ?CParam {
      foreach ( $this->params as $param ) {
         if ( $param->name == $name ) {
            return $param;
         }
      }
      return null;
   }

   public function outputPrototype( CContent $content ): void {
      $this->outputHeader( $content );
      $content->write( ";\n" );
   }

   private function outputHeader( CContent $content ): void {
      $content->writeType( RUNTIMEVALUE_VOID );
      $content->write( ' %s', $this->name );
      $content->write( '( ' );
      if ( count( $this->params ) > 0 ) {
         $added = false;
         foreach ( $this->params as $param ) {
            if ( $added ) {
               $content->write( ', ' );
            }
            $this->outputParam( $content, $param );
            $added = true;
         }
      }
      else {
         $content->writeType( RUNTIMEVALUE_VOID );
      }
      $content->write( ' )' );
   }

   private function outputParam( CContent $content, CParam $param ): void {
      $content->writeType( $param->type );
      $content->write( '*' );
      $content->write( ' p%d', $param->index );
   }

   public function output( CContent $content ): void {
      $this->allocateVarIndexes();
      $this->outputHeader( $content );
      $content->write( ' ' );
      $this->outputBody( $content );
   }

   private function outputBody( CContent $content ): void {
      $content->append( "{\n" );
      $content->indent();
      // Variables.
      foreach ( $this->vars as $var ) {
         $var->output( $content );
      }
      // Parameter assignment to local variables.
      foreach ( $this->params as $param ) {
         $content->write( "%s = *p%d;\n", $param->var->name(), $param->index );
      }
      $this->body->outputStmtList( $content );
      $content->dedent();
      $content->append( "}\n" );
   }
}
