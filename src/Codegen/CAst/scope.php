<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

class Binding {
   public string $name;
   public ?CVar $var = null;
}

class Scope {
   public array $bindings = [];
}

class ScopeList {
   public Scope $scope;

   private array $scopes;

   public function __construct() {
      $this->scopes = [];
      $this->push();
   }

   public function push(): void {
      $this->scope = new Scope();
      array_unshift( $this->scopes, $this->scope );
   }

   public function pop(): ?Scope {
      if ( count( $this->scopes ) > 1 ) {

         $oldScope = array_shift( $this->scopes );
         //$this->transferValues( $oldScope );
         $this->scope = $this->scopes[ 0 ];
         return $oldScope;
      }
      return null;
   }

   private function transferValues( Scope $scope ): void {
      foreach ( $scope->bindings as $binding ) {
         if ( $binding->var !== null ) {

         }
      }
   }

   public function get( string $name ): ?Binding {
      foreach ( $this->scopes as $scope ) {
         if ( array_key_exists( $name, $scope->bindings ) ) {
            return $scope->bindings[ $name ];
         }
      }
      return null;
   }

   public function create( string $name ): Binding {
      if ( array_key_exists( $name, $this->scope->bindings ) ) {
         return $this->scope->bindings[ $name ];
      }
      $binding = new Binding();
      $binding->name = $name;
      $this->scope->bindings[ $name ] = $binding;
      return $binding;
   }
}
