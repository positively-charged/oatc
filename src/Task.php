<?php

declare( strict_types = 1 );

/**
 * Holds common data that is shared between the different phases of the
 * compilation.
 */
class Task {
   public User $user;
   public Structure $bool;
   /**
    * All the modules that are parsed during compilation.
    *
    * @var Module[]
    */
   public array $modules = [];
   /**
    * The module being compiled.
    */
   public Module $module;
   public \Checking\BuiltinModule $builtinModule;
   public ?Module $prelude = null;
   public array $bundlesToStructs = [];
   /** @var string[] */
   public array $strings = [];

   public function findLoadedModule( string $path ): ?Module {
      foreach ( $this->modules as $module ) {
         if ( $module->path == $path ) {
            return $module;
         }
      }
      return null;
   }

   public function internString( string $value ): int {
      $index = array_search( $value, $this->strings, true );
      if ( $index === false ) {
         $count = array_push( $this->strings, $value );
         return $count - 1;
      }
      else {
         return $index;
      }
   }
}
