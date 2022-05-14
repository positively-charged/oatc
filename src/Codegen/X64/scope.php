<?php

declare( strict_types = 1 );

namespace Codegen\X64;

class Binding {
   public string $name;
   public Slot $slot;
   public ?Value $value = null;
}

class Scope {
   public array $bindings = [];
   public array $allocs = [];
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
         foreach ( $this->scope->bindings as $binding ) {
            if ( $binding->alloc != null ) {
               --$binding->alloc->numLabelsAttached;
            }
         }

         // Move any unallocated blocks to the parent scope.
         foreach ( $this->scope->allocs as $alloc ) {
            if ( $alloc->numLabelsAttached > 0 ) {
               array_push( $this->scopes[ 1 ]->allocs, $alloc );
            }
         }

         $oldScope = array_shift( $this->scopes );
         $this->scope = $this->scopes[ 0 ];
         return $oldScope;
      }
      return null;
   }

   public function get( string $name ): Binding {
      foreach ( $this->scopes as $scope ) {
         if ( array_key_exists( $name, $scope->bindings ) ) {
            return $scope->bindings[ $name ];
         }
      }
      $binding = new Binding();
      $binding->name = $name;
      $this->scope->bindings[ $name ] = $binding;
      return $binding;
   }
}
