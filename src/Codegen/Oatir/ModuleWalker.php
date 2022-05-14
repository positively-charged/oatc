<?php

declare( strict_types = 1 );

namespace Codegen\Oatir;

use Typing\TypeChecker;

class ModuleWalker {
   private \Task $task;
   private \Module $module;
   private Archive $archive;
   private ScopeList $scopeList;
   private TypeChecker $typeChecker;

   public function __construct( \Module $module, \Task $task,
      Archive $archive, ScopeList $scopeList, TypeChecker $typeChecker ) {
      $this->module = $module;
      $this->task = $task;
      $this->archive = $archive;
      $this->scopeList = $scopeList;
      $this->typeChecker = $typeChecker;
   }

   public function visitModule(): void {
      $this->createStrings();
      $this->createFuncs();
      $this->visitFuncsBodies();
   }

   public function createStrings(): void {
      $s = new IndexedString();
      $s->value = "abc\n";
      $s->index = 0;
      array_push( $this->archive->strings, $s );
      $s = new IndexedString();
      $s->value = "123\n";
      $s->index = 1;
      array_push( $this->archive->strings, $s );
   }

   public function createFuncs(): void {
      foreach ( $this->module->items as $item ) {
         if ( $item instanceof \Func ) {
            $this->createFunc( $this->archive, $item );
         }
      }
      $this->createPrintfFunc();
   }

   private function createPrintfFunc(): void {
      $irFunc = new Func();
      $irFunc->name = "printf";
      $irFunc->extern = true;
      $irFunc->variadic = true;
      array_push( $this->archive->funcs, $irFunc );
      $this->archive->printf = $irFunc;
   }

   private function createFunc( Archive $archive, \Func $func ): void {
      $irFunc = new Func();
      $irFunc->name = $func->name;
      $irFunc->global = $func->visible;
      $irFunc->func = $func;
      array_push( $archive->funcs, $irFunc );
   }

   public function visitFuncsBodies(): void {
      foreach ( $this->archive->funcs as $func ) {
         if ( $func->func !== null ) {
            $this->visitFuncBody( $this->archive, $func );
         }
      }
   }

   private function visitFuncBody( Archive $archive, Func $func ): void {
      $runner = new Runner( $archive );
      foreach ( $func->func->params as $param ) {
         $irParam = new Param();
         $irParam->type = TYPE_ISIZE;
         array_push( $func->params, $irParam );
         $irParam->slot = $runner->allocSlot();
         $binding = $this->scopeList->get( $param->name );
         $binding->slot = $irParam->slot;
      }
      $walker = new ExprWalker( $this->task, $this->archive, $this->scopeList,
         $this->typeChecker );

      $runner->add( OP_NOP );

/*
      $instruction = new SetStrInstruction();
      $instruction->string = $this->archive->strings[ 1 ];
      $instruction->destination = $runner->allocSlot();
      $instruction->destination->type = TYPE_ISIZE;
      $runner->appendInstruction( $instruction );

      $call = new CallInstruction();
      $call->func = $this->archive->printf;
      array_push( $call->args, $instruction->destination );
      $runner->appendInstruction( $call );
*/

      $walker->visitBlockStmt( $runner, $func->func->body );
      $func->blocks = $runner->finalize();
   }
}
