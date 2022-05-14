<?php

declare( strict_types = 1 );

namespace Codegen\Php;

use Typing\TypeChecker;

const STR_TABLE_VAR = 'strtbl';

class ModuleWalker {
   private \Task $task;
   private \Module $module;
   private PhpScript $script;
   private ScopeList $scopeList;
   private TypeChecker $typeChecker;

   public function __construct( \Module $module, \Task $task,
      PhpScript $script, ScopeList $scopeList, TypeChecker $typeChecker ) {
      $this->module = $module;
      $this->task = $task;
      $this->script = $script;
      $this->scopeList = $scopeList;
      $this->typeChecker = $typeChecker;
   }

   public function visitModule(): void {
      #$this->createStringTable( $unit, $this->module );
      $this->createObjects();
      #$this->fillStructs();
      #$this->createCFuncs( $unit );
      #$this->createCVars( $unit );

      //$this->createObjects();
      //$this->fillStructs();
      //$this->fillFuncs();
      //$this->fillVars();
      #$this->fillFuncs();
      foreach ( $this->module->items as $item ) {
         if ( $item instanceof \Func ) {
            $this->visitFunc( $this->script, $item );
         }
      }
   }

   private function createObjects(): void {
      foreach ( $this->module->items as $item ) {
         switch ( $item->nodeType ) {
         case \NODE_ENUM:
         case \NODE_STRUCTURE:
         case \NODE_TRAIT:
            $this->createStruct( $item->name );
            break;
         case \NODE_FUNC:
            $this->createFunc( $item );
            break;
         }
      }
   }

   private function createFunc( \Func $func ): void {
      $phpFunc = new PhpFunc();
      $phpFunc->name = $func->name;
      $this->script->symbols[ $phpFunc->name ] = $phpFunc;
      $this->script->funcsToPhpfuncs[ $phpFunc->name ] = $phpFunc;
      array_push( $this->script->funcs, $phpFunc );
   }

   private function visitFunc( PhpScript $script, \Func $func ): void {
      $phpFunc = $script->funcsToPhpfuncs[ $func->name ];
      if ( $func->body != null ) {
         $phpFunc->body = $this->visitBlockStmt( $func->body );
      }
   }

   private function visitBlockStmt( \BlockStmt $stmt ): PhpBlockStmt {
      $walker = new ExprWalker( $this->task, $this->script, $this->scopeList,
         $this->typeChecker );
      return $walker->visitBlockStmt( $stmt );
   }

   private function fillFuncs(): void {
      foreach ( $this->module->items as $item ) {
         if ( $item->nodeType == \NODE_FUNC ) {
            if ( ! $item->virtual ) {
               $this->visitFunc( $this->unit, $item );
            }
         }
         else if ( $item->nodeType == \NODE_STRUCTURE ) {
            foreach ( $item->impls as $impl ) {
               foreach ( $impl->funcs as $func ) {
                  if ( ! $func->virtual ) {
                     $this->visitTraitFunc( $this->unit, $func, $item, $item->name . "x" .
                        $impl->traitName . 'x' );
                  }
               }
            }
            /*
            if ( $item->methods != null ) {
               foreach ( $item->methods->funcs as $func ) {
                  if ( ! $func->virtual ) {
                     $this->visitFunc( $unit, $func, $item->name . "x" );
                  }
               }
            }*/
         }
      }
   }
}
