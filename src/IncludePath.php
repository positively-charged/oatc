<?php

class Prefix {
   public string $name;
   public string $path;
}

class IncludePath {
   private string $defaultDir;
   private array $prefixes;

   public function __construct( string $defaultDir ) {
      $this->defaultDir = $defaultDir;
      $this->prefixes = [];
   }

   public function addPrefix( string $name, string $path ): void {
      $prefix = new Prefix();
      $prefix->name = $name;
      $prefix->path = $path;
      $this->prefixes[ $name ] = $prefix;
   }

   public function findModule( string $prefix, string $name ): ?string {
      $dir = $this->defaultDir;
      if ( array_key_exists( $prefix, $this->prefixes ) ) {
         $dir = $this->prefixes[ $prefix ]->path;
      }
      $path = sprintf( '%s/%s.oat', $dir, $name );
      if ( file_exists( $path ) ) {
         return $path;
      }
      return null;
   }
}
