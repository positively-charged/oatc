<?php

declare( strict_types = 1 );

namespace Ctce;

/*
class Binding {
   public string $name;
   public ?Slot $slot = null;
}

class Slot {
   public \Type $type;
   public $value;
} */

class Result {
   public ?\Binding $binding;
   public ?\Slot $slot;
   public bool $instantiable;

   public function __construct() {
      $this->binding = null;
      $this->slot = null;
      $this->instantiable = false;
   }
}

class StructInstance {
   public \Structure $structure;
   /**
    * @var \Binding[]
    */
   public array $members = [];
}

class EnumInstance {
   public \Enumerator $enumerator;
   /**
    * @var \Binding[]
    */
   public array $params = [];
}

interface Iter {
   public function next(): ?Result;
}

class VecInstance {
   public array $values = [];
   public int $size = 0;

   public function iterate(): Iter {
      return new VecIter( $this );
   }
}

class VecIter implements Iter {
   private VecInstance $vec;
   private int $pos;

   public function __construct( VecInstance $vec ) {
      $this->vec = $vec;
      $this->pos = 0;
   }

   public function next(): ?Result {
      if ( $this->pos < $this->vec->size ) {
         $result = new Result();
         $result->slot = $this->vec->values[ $this->pos ];
         ++$this->pos;
         return $result;
      }
      return null;
   }
}

class Scope {
   public array $bindings = [];
   public array $allocs = [];
}

class ScopeTable {
   private array $scopes;
   private Scope $activeScope;

   public function __construct() {
      $this->scopes = [];
      $this->push();
      $this->activeScope = $this->scopes[ 0 ];
   }

   public function push(): void {
      $this->activeScope = new Scope();
      array_unshift( $this->scopes, $this->activeScope );
   }

   public function pop(): void {
      if ( count( $this->scopes ) > 1 ) {
      /*
         foreach ( $this->activeScope->bindings as $binding ) {
            if ( $binding->alloc != null ) {
               --$binding->alloc->numLabelsAttached;
            }
         }

         // Move any unallocated blocks to the parent scope.
         foreach ( $this->activeScope->allocs as $alloc ) {
            if ( $alloc->numLabelsAttached > 0 ) {
               array_push( $this->scopes[ 1 ]->allocs, $alloc );
            }
         }
      */

         $oldScope = array_shift( $this->scopes );
         $this->activeScope = $this->scopes[ 0 ];
      }
   }

   public function createBinding( string $name ): Binding {
      if ( ! array_key_exists( $name, $this->activeScope->bindings ) ) {
         $binding = new Binding();
         $binding->name = $name;
         $this->activeScope->bindings[ $name ] = $binding;
         return $binding;
      }
      else {
         throw new \Exception();
      }
   }

   public function get( string $name ): Binding {
      foreach ( $this->scopes as $scope ) {
         if ( array_key_exists( $name, $scope->bindings ) ) {
            return $scope->bindings[ $name ];
         }
      }
      throw new \Exception();
   }
}
