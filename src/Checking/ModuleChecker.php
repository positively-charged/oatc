<?php

declare( strict_types = 1 );

namespace Checking;

use Ctce\Evaluator;
use ImportItem;
use Module;
use Typing\Describer;
use Typing\InstanceChecker;
use Typing\Presenter;
use Typing\SamenessChecker;
use Typing\Type;
use Typing\TypeChecker;

define( 'INT32_MIN', -2147483648 );
define( 'INT32_MAX', +2147483647 );

class ModuleChecker {
   private \User $user;
   private \Module $module;
   private \Scope $scope;
   private \Typing\TypeChecker $typeChecker;
   private DeclChecker $declChecker;
   private ExprChecker $exprChecker;
   private SimpleExprChecker $simpleExprChecker;
   private \Task $task;

   public function __construct( \Task $task, \Scope $scope,
      TypeChecker $typeChecker, private Describer $typeDescriber,
      private Presenter $typePresenter,
      private InstanceChecker $instanceChecker,
      private SamenessChecker $samenessChecker ) {
      $this->user = $task->user;
      $this->module = $task->module;
      $this->scope = $scope;
      $this->task = $task;
      $this->typeChecker = $typeChecker;
      #$this->evaluator = new Evaluator( $task->user, $this->scopeList,
      #   $this->typeChecker );
      #$this->stmtChecker = new StmtChecker( $this->scopeList, $this->task,
      #   $this->typeChecker );
      $evaluator = new Evaluator( $task->user, $scope, $task->builtinModule,
         $typeChecker, $this->typeDescriber, $this->typePresenter );
      $simpleExprChecker = new SimpleExprChecker( $this->scope, $this->task,
         $this->typeChecker, $this->typeDescriber, $this->typePresenter,
         $this->instanceChecker, $this->samenessChecker, $evaluator );
      $this->exprChecker = new ExprChecker( $task->user,
         $typeChecker, $simpleExprChecker, $scope, $this->typeDescriber,
         $this->typePresenter, $this->instanceChecker, $this->samenessChecker,
         $evaluator, $this, $this->task );
      $this->declChecker = new DeclChecker( $this, $this->scope,
         $this->task, $this->typeChecker, $this->exprChecker,
         $simpleExprChecker,
         $this->typeDescriber,
         $this->typePresenter,
         $this->instanceChecker );
      $simpleExprChecker->setCheckers( $this, $this->declChecker,
         $this->exprChecker );
      $this->simpleExprChecker = $simpleExprChecker;
   }

   public function check(): void {
      //$this->createBuiltinModule();
      $this->bindNames();
      $this->createDefaultModulePrefixes();
      $this->performImports();
      //$this->checkPrototypes();
      $this->checkItems();

      #$itemFinder = new ItemFinder( $this->user, $this->module );
      #$item = $itemFinder->searchBinding( 'Int' );
      #var_dump( $item );

      /*
      foreach ( $this->module->funcs as $func ) {
         printf( "%s == ", $func->name );;
         var_dump( $func->evaluable );
      }
      exit( 1 );
      */
   }

   private function bindNames(): void {
      foreach ( $this->task->modules as $module ) {
         $prevModule = $this->changeModule( $module );
         foreach ( $module->items as $item ) {
            $this->bindModuleItem( $item );
         }
         $this->changeModule( $prevModule );
      }
   }

   private function bindModuleItem( \Node $item ): void {
      if (
         $item instanceof \Func ||
         $item instanceof \Generic ||
         $item instanceof \TraitObj ||
         $item instanceof \Constant ||
         $item instanceof \TypeAlias ) {
         $this->scope->bind( $item->name, $item );
      }
      else if ( $item instanceof \Structure ) {
         if ( ! empty( $item->name ) ) {
            if ( $item->defined || count( $item->impls ) === 0 ) {
               $binding = $this->scope->replace( $item->name );
               if ( $binding !== null && $binding->node instanceof \Structure ) {
                  if ( ! $binding->node->defined ) {
                     $binding->node = $item;
                  }
               }
               else {
                  $this->scope->bind( $item->name, $item );
               }
            }
         }
      }
      else if ( $item instanceof \Enumeration ) {
         if ( ! empty( $item->name ) ) {
            $this->scope->bind( $item->name, $item );
         }
         foreach ( $item->body as $enumerator ) {
            $this->scope->bind( $enumerator->name, $enumerator );
         }
      }
      else {
         if ( ! (
            $item instanceof \Import ||
            $item instanceof \Let ) ) {
            UNREACHABLE();
         }
      }
   }

   private function createDefaultModulePrefixes(): void {
      foreach ( $this->task->modules as $module ) {
         $module->prefixes[ 'mod' ] = $module;
      }
   }

   private function performImports(): void {
      foreach ( $this->task->modules as $module ) {
         $prevModule = $this->changeModule( $module );
         foreach ( $module->imports as $import ) {
            #$this->createModuleAlias( $module, $import );
            #$this->importSelection( $import );
            $this->performImport( $import );
         }
         $this->changeModule( $prevModule );
      }
   }

   private function performImport( \Import $import ): void {
      foreach ( $import->selections as $selection ) {
         $this->importSelection( $import, $selection );
      }
   }

   private function importSelection( \Import $import,
      \ImportSelection $selection ): void {
      // Non-module item:
      if ( $selection->item != null ) {
         $this->importItem( $selection->module, $selection->item,
            $selection->glob, $selection->alias );
      }
      // Module:
      else {
         if ( $selection->alias != null ) {
            $this->createModuleAlias( $selection->module,
               $selection->alias->value,
               $selection->alias->pos );
         }
         else {
            if ( ! $selection->glob ) {
               if ( $selection->path->useCurrentModule ) {
               }
               else {
                  $this->createModuleAlias( $selection->module,
                     $selection->path->tail->name,
                     $selection->path->tail->pos );
               }
            }
         }
         foreach ( $selection->selections as $nestedSelection ) {
            $this->importSelection( $import, $nestedSelection );
         }
      }
   }

   private function createModuleAlias( \Module $module, string $alias,
      \Lexing\Position $pos ): void {
      if ( ! array_key_exists( $alias, $this->module->prefixes ) ) {
         $this->module->prefixes[ $alias ] = $module;
      }
      else {
         if ( $module != $this->module->prefixes[ $alias ] ) {
            $this->user->diag( DIAG_ERR, $pos,
               "module alias `%s` already used", $alias );
            $this->user->bail();
         }
      }
   }

   private function importItem( \Module $module, ImportItem $item, bool $glob,
      ?\Name $alias ): void {
      $binding = $module->scope->getInModule( $item->name );
      if ( $binding != null ) {
         $item->object = $binding->node;
         if ( $glob ) {
            $this->user->diag( DIAG_ERR, $item->pos,
               'item `%s` does not support a glob import', $item->name );
            $this->user->bail();
         }
         else {
            $name = $item->name;
            if ( $alias != null ) {
               $name = $alias->value;
               $item->pos = $alias->pos;
            }
            $this->scope->bind( $name, $item );
         }
      }
      else {
         $this->user->diag( DIAG_ERR, $item->pos,
            '`%s` not found in module `%s`', $item->name,
            $module->path );
         $this->user->bail();
      }
   }

   private function checkPrototypes(): void {
      foreach ( $this->task->modules as $module ) {
         $this->checkPrototypesInModule( $module );
      }
   }

   private function checkPrototypesInModule( \Module $module ): void {
      $this->changeModule( $module );
      $module->checkedPrototypes = true;
      /*
      foreach ( $module->importedModules as $importedModule ) {
         if ( ! $importedModule->checkedPrototypes ) {
            $this->checkPrototypesInModule( $importedModule );
            $this->checkItems( $importedModule );
         }
      }*/
      foreach ( $module->items as $item ) {
         $this->checkPrototype( $item );
      }
   }

   private function checkPrototype( \Node $item ): void {
      if ( $item instanceof \Func ) {
         $this->declChecker->checkFuncPrototype( $item );
      }
      else if ( $item instanceof \Structure ) {
         if ( ! $item->resolved ) {
            $this->declChecker->checkStructPrototype( $item );
         }
      }
      else if ( $item instanceof \Constant ) {
         $this->declChecker->checkConstant( $item );
      }
      else {
         if ( ! (
            $item instanceof \TraitObj ||
            $item instanceof \Generic ||
            $item instanceof \Let ||
            $item instanceof \Enumeration ||
            $item instanceof \Import ||
            $item instanceof \TypeAlias ) ) {
            UNREACHABLE();
         }
      }
   }

   private function checkItems(): void {
      foreach ( $this->task->modules as $module ) {
         $this->changeModule( $module );
         foreach ( $module->items as $item ) {
            $this->checkModuleItem( $item );
         }
      }
   /*
      $triggerErr = false;
      while ( true ) {
         $resolved = false;
         $unresolved = false;
         foreach ( $this->task->modules as $module ) {
            $this->scopeList->changeModule( $module );
            #var_dump( $module->path );
            foreach ( $module->items as $item ) {
               try {
                  if ( ! property_exists( $item, 'resolved' ) ) {
                     $item->resolved = false;
                  }
                  if ( ! $item->resolved ) {
                  if ( property_exists( $item, 'name' ) ) {
                  #printf( "%s", $item->name );
                  }
                     $this->checkModuleItem( $item );
                     if ( property_exists( $item, 'name' ) ) {
                     #printf( " resolved\n" );
                     }
                     $item->resolved = true;
                     $resolved = true;
                  }
               }
               catch ( Unresolved $err ) {
                  #printf( "\n" );
                  // When we fail to resolve anymore items, we can no longer
                  // continue.
                  if ( $triggerErr ) {
                     $this->user->diag( DIAG_ERR, $err->pos,
                        '`%s` could not be resolved', $err->object );
                     $this->user->bail();
                  }
                  $unresolved = true;
               }
            }
         }
         if ( $unresolved && ! $resolved ) {
            $triggerErr = true;
         }
         if ( ! $unresolved ) {
            break;
         }
      }
      */
   }

   private function checkModuleItems( \Module $module ): void {
   }

   private function checkModuleItem( \Node $item ): void {
      if ( $item instanceof \Let ) {
         $this->checkLet( $item );
      }
      else if ( $item instanceof \Constant ) {
         $this->declChecker->checkConstant( $item );
      }
      else if ( $item instanceof \Enumeration ) {
         $this->declChecker->checkEnum( $item );
      }
      else if ( $item instanceof \Structure ) {
         if ( ! $item->resolved ) {
            $this->declChecker->checkStruct( $item );
         }
      }
      else if ( $item instanceof \TraitObj ) {
         $this->declChecker->checkTrait( $item );
      }
      else if ( $item instanceof \Func ) {
         $this->declChecker->checkFunc( $item );
      }
      else if ( $item instanceof \Generic ) {
         $this->declChecker->checkGeneric( $item );
      }
      else {
         switch ( get_class( $item ) ) {
         case \TypeAlias::class:
         case \Constant::class:
            break;
         default:
            var_dump( get_class( $item));
            throw new \Exception();
         }
      }
   }

   private function checkLet( \Let $item ): void {
      $this->simpleExprChecker->checkLet( $item );
   }

   public function isStructInModule( \Structure $structure ): bool {
      $binding = $this->scope->get( $structure->name );
      return ( $binding !== null && $binding->node === $structure );
   }

   public function checkItemOutOfOrder( \Module $module, \Node $item ): void {
      $prevModule = $this->changeModule( $module );
      if ( $item instanceof \Constant ) {
         $this->declChecker->checkConstant( $item );
      }
      else if ( $item instanceof \Enumerator ) {
         $this->declChecker->checkEnum( $item->enumeration );
      }
      else if ( $item instanceof \Structure ) {
         $this->declChecker->checkStruct( $item );
      }
      else if ( $item instanceof \Func ) {
         $this->declChecker->checkFunc( $item );
         $item->evaluable = true;
      }
      else {
         UNREACHABLE();
      }
      $this->changeModule( $prevModule );
   }

   public function getPrefixModule( string $name ): ?\Module {
      if ( $name == '' ) {
         return $this->module;
      }
      else {
         if ( array_key_exists( $name, $this->module->prefixes ) ) {
            return $this->module->prefixes[ $name ];
         }
         return null;
      }
   }

   private function changeModule( \Module $module ): \Module {
      $prevModule = $this->scope->changeModule( $module );
      $this->module = $module;
      return $prevModule;
   }

   public function appendTupleStruct( \Structure $structure ): void {
      $this->module->tuples[] = $structure;
   }
}
