<?php

declare( strict_types = 1 );

class SearchResult {
   public Binding $binding;
   public Module $moduleFoundIn;
}

class ItemFinder {
   private User $user;
   /** @var ScopeFloor[] */
   private array $floors;
   private Module $module;
   private ?SearchResult $result;
   private bool $dupErr;

   /**
    * @param ScopeFloor[] $floors
    */
   public function __construct( User $user, array $floors,
      Module $module ) {
      $this->user = $user;
      $this->floors = $floors;
      $this->module = $module;
      $this->result = null;
      $this->dupErr = false;
   }

   private function getInModule( string $name ): ?Binding {
      foreach ( $this->floors as $floor ) {
         if ( array_key_exists( $name, $floor->bindings ) ) {
            return $floor->bindings[ $name ];
         }
      }
      return null;
   }

   public function searchModule( string $name,
      ?Lexing\Position $pos = null ): ?Module {
      $this->search( $name, $pos );
      return $this->result?->moduleFoundIn;
   }

   public function search( string $name, ?Lexing\Position $pos = null,
      ?Module $module = null ): ?SearchResult {
      // Search in local scope.
      // Search in current module.
      // Search in imported modules.
      $this->result = null;
      if ( ! $this->searchInLocalScope( $name, $pos ) ) {
         if ( ! $this->searchInModule( $module ?: $this->module,
            $name, $pos ) ) {
            $this->searchInImportedModules( $module ?: $this->module, $name,
               $pos );
            if ( $this->dupErr ) {
               $this->user->bail();
            }
         }
      }
      return $this->result;
   }

   private function searchInLocalScope( string $name,
      ?Lexing\Position $pos ): bool {
      $binding = $this->getInModule( $name );
      if ( $binding?->node != null ) {
         $this->processMatch( $this->module, $binding, $pos );
         return true;
      }
      return false;
   }

   private function searchInModule( Module $module, string $name,
      ?Lexing\Position $pos = null ): bool {
      if ( ( $binding = $module->scope->get( $name ) ) != null ) {
         if ( $binding->node == null ||
            $this->isItemVisible( $module, $binding->node ) ) {
            $this->processMatch( $module, $binding, $pos );
            return true;
         }
      }
      return false;
   }

   // TODO: Determine what to do when a name is found in an imported module and
   // is also found in one of the imported modules of the imported module. How
   // do we let the user disambiguate in this case?
   private function searchInImportedModules( Module $module, string $name,
      ?Lexing\Position $pos = null ): void {
      // TODO: this is ugly, change it.
      $importedModules = $module->visibleImportedModules;
      if ( $module === $this->module ) {
         $importedModules = $module->importedModules;
      }
      foreach ( $importedModules as $importedModule ) {
         $this->searchInImportedModule( $importedModule, $name, $pos );
      }
   }

   private function searchInImportedModule( Module $module, string $name,
      ?Lexing\Position $pos = null ): void {
      $binding = $module->scope->get( $name );
      if ( $binding != null &&
         $this->isItemVisible( $module, $binding->node ) ) {
         $this->processMatch( $module, $binding, $pos );
      }
      $this->searchInImportedModules( $module, $name, $pos );
   }

   private function isItemVisible( Module $owner, Node $item ): bool {
      if ( $item instanceof Func ||
         $item instanceof Generic ||
         $item instanceof Structure ||
         $item instanceof Enumeration ||
         $item instanceof Enumerator ||
         $item instanceof ImportItem ||
         $item instanceof Constant ||
         $item instanceof TypeAlias ) {
         return ( $item->visible || $this->module === $owner );
      }
      else if (
         $item instanceof Let ||
         $item instanceof Seen ||
         $item instanceof ForItem ) {
         return true;
      }
      else {
         UNREACHABLE( 'unhandled item `%s`', get_class( $item ) );
      }
      return false;
   }

   private function processMatch( Module $owner, Binding $binding,
      ?Lexing\Position $pos ): void {
      if ( $this->result == null ) {
         $result = new SearchResult();
         $result->binding = $binding;
         $result->moduleFoundIn = $owner;
         $this->result = $result;
      }
      else {
         if ( $this->result->binding->node !== $binding->node ) {
            if ( ! $this->dupErr ) {
               $this->user->diag( DIAG_ERR, $pos,
                  "multiple instances of `%s` are visible", $binding->name );
               $this->user->diag( DIAG_NOTICE,
                  $this->getItemPos( $this->result->binding->node ),
                  "`%s` found here", $binding->name );
               $this->dupErr = true;
            }
            $this->user->diag( DIAG_NOTICE, $this->getItemPos( $binding->node ),
               "`%s` also found here", $binding->name );
         }
      }
   }

   private function getItemPos( Node $item ): Lexing\Position {
      if ( $item instanceof Func ||
         $item instanceof Enumeration ||
         $item instanceof ImportItem ||
         $item instanceof Constant ||
         $item instanceof Structure ) {
         return $item->pos;
      }
      else {
         UNREACHABLE();
      }
   }
}
