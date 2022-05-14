<?php

declare( strict_types = 1 );

namespace Codegen\X64;

use Typing\TypeChecker;

class ModuleWalker {
   private \Task $task;
   private \Module $module;
   private Assembly $assembly;
   private ScopeList $scopeList;
   private TypeChecker $typeChecker;

   public function __construct( \Module $module, \Task $task,
      Assembly $assembly, ScopeList $scopeList, TypeChecker $typeChecker ) {
      $this->module = $module;
      $this->task = $task;
      $this->assembly = $assembly;
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
      array_push( $this->assembly->strings, $s );
      $s = new IndexedString();
      $s->value = "123\n";
      $s->index = 1;
      array_push( $this->assembly->strings, $s );
   }

   public function createFuncs(): void {
      foreach ( $this->module->items as $item ) {
         if ( $item instanceof \Func ) {
            $this->createFunc( $this->assembly, $item );
         }
      }
      $this->createPrintfFunc();
   }

   private function createPrintfFunc(): void {
      $irFunc = new Func();
      $irFunc->name = "printf";
      $irFunc->extern = true;
      $irFunc->variadic = true;
      array_push( $this->assembly->funcs, $irFunc );
      $this->assembly->printf = $irFunc;
   }

   private function createFunc( Assembly $assemlby, \Func $func ): void {
      // Single-use functions are inlined, so there is no need to output a
      // concrete definition of the function.
      //if ( $func->numCalls > 1 || $func->visible ) {
         $irFunc = new Func();
         $irFunc->name = $func->name;
         $irFunc->global = $func->visible;
         $irFunc->inline = ( $func->numCalls == 1 );
         $irFunc->func = $func;
         $irFunc->returnsValue = ( $func->returnType !== null );
         array_push( $assemlby->funcs, $irFunc );
      //}
   }

   public function visitFuncsBodies(): void {
      foreach ( $this->assembly->funcs as $func ) {
         if ( $func->func !== null ) {
            $this->visitFuncBody( $this->assembly, $func );
         }
      }
   }

   private function visitFuncBody( Assembly $assemlby, Func $func ): void {
      $stackFrame = new StackFrame( $func );
      $seq = new Sequence();
      //$registerFile = new RegisterFile( $seq, $stackFrame );
      $runner = new Machine( $assemlby, $seq );
      foreach ( $func->func->params as $param ) {
         $irParam = new Param();
         $irParam->name = $param->name;
         $irParam->type = TYPE_ISIZE;
         array_push( $func->params, $irParam );
         $irParam->slot = $runner->allocSlot();
         $binding = $this->scopeList->get( $param->name );
         $binding->slot = $irParam->slot;
      }
      $walker = new ExprWalker( $this->task, $this->assembly, $this->scopeList,
         $this->typeChecker, $runner );

      /*
            $runner->add( OP_NOP );

            $instruction = new SetStrInstruction();
            $instruction->string = $this->assemlby->strings[ 1 ];
            $instruction->destination = $runner->allocSlot();
            $instruction->destination->type = TYPE_ISIZE;
            $runner->appendInstruction( $instruction );

            $call = new CallInstruction();
            $call->func = $this->assemlby->printf;
            array_push( $call->args, $instruction->destination );
            $runner->appendInstruction( $call );
      */

      $walker->visitFuncBlockStmt( $func->func->body );
      $func->blocks = $runner->finalize();
   }
}
