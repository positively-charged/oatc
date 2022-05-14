<?php

declare( strict_types = 1 );

namespace Codegen\X64;

use \Codegen\Oatir;

class CodeGenerator {
   private \Task $task;
   private \Module $module;
   private \Typing\TypeChecker $typeChecker;

   public function __construct( \Task $task,
      \Typing\TypeChecker $typeChecker ) {
      $this->task = $task;
      $this->module = $task->module;
      $this->typeChecker = $typeChecker;
   }

   public function publish( string $outputFile ): void {
      $assembly = new Assembly();
      $scopeList = new ScopeList();

      $walker = new ModuleWalker( $this->module, $this->task, $assembly,
         $scopeList, $this->typeChecker );
      $walker->visitModule();

      #$this->visitAssembly( $assembly );
      $registerAllocator = new RegisterAllocator( $assembly );
      $registerAllocator->allocate();

      $writer = new Writer();
      $writer->write( $assembly, $outputFile );
   }

   private function visitAssembly( Assembly $assembly ): void {
   /*
      foreach ( $assembly->strings as $string ) {
         $s = new StringEntry();
         $s->value = $string->value;
         $s->id = $string->index;
         array_push( $assembly->strings, $s );
      } */
      foreach ( $assembly->funcs as $func ) {
         $this->publishFunc( $assembly, $func );
      }
   }

   private function publishFunc( Assembly $assembly,
      Func $func ): void {
      #$this->allocParamRegisters( $x64Func );
      if ( $func->func !== null ) {
         $this->visitBlocks( $assembly, $func );
      }
   }

   private function allocParamRegisters( Oatir\Func $func ): void {
   }

   private function visitBlocks( Assembly $assembly, Func $func ): void {
      /*
      $seq = new Sequence();

      $intArgsAdded = 0;
      foreach ( $func->params as $param ) {
         $registers = [ REG_RDI, REG_RSI, REG_RDX, REG_RCX, REG_R8, REG_R9 ];
         // Pass argument in register.
         if ( $intArgsAdded < count( $registers ) ) {
            $reg = $registers[ $intArgsAdded ];
            $seq->allocConcreteReg( $param->slot, $reg );
            ++$intArgsAdded;
         }
         // Pass argument on the stack.
         else {

         }
      } */

      foreach ( $func->blocks as $block ) {
         $this->visitBlock( $block );
      }
     // $seq->makeReturnable();
      //return $seq;
   }

   private function visitBlock( Block $block ): void {
      $seq->addM( OP_LABEL, sprintf( '.b%s', $block->id ) );
      foreach ( $block->instructions as $instruction ) {
         $this->visitInstruction( $instruction );
      }
      /*
      if ( $block?->exitJump !== null ) {
         if ( $block->exitJump->cond !== null ) {
            $label = sprintf( '.b%s', $block?->exitJump->onFalse->id );
            $reg = $seq->getReg( $block->exitJump->cond );
            $seq->addRI( OP_TEST, $reg, $reg );
            $seq->addM( OP_JZ, $label );
            $label = sprintf( '.b%s', $block?->exitJump->onTrue->id );
            $seq->addM( OP_JMP, $label );
         }
         else {
            $label = sprintf( '.b%s', $block?->exitJump->dst->id );
            $seq->addM( OP_JMP, $label );
         }
      }*/
   }

   private function visitInstruction( Instruction $instruction ): void {
      if ( $instruction instanceof BinaryInstruction ) {
         $this->visitBinaryInstruction( $instruction );
      }
      else if ( $instruction instanceof CallInstruction ) {
         $this->visitCall( $seq, $instruction );
      }
      else if ( $instruction instanceof RetInstruction ) {
         if ( $instruction->value !== null ) {
            $reg = $seq->getReg( $instruction->value );
            $seq->addRR( OP_MOV_R64R64, REG_RAX, $reg );
         }
         $seq->add( OP_RET );
      }
      else if ( $instruction instanceof MovInstruction ) {

      }
      else if ( $instruction instanceof SetImmInstruction ) {
         $reg = $seq->allocReg( $instruction->destination );
         $seq->addRI( OP_MOV_R64I64, $reg, $instruction->value );
      }
      else if ( $instruction instanceof SetStrInstruction ) {
         $reg = $seq->allocReg( $instruction->destination );
         $label = sprintf( 's%s', $instruction->string->index );
         $seq->addRM( OP_MOV_R64M64, $reg, $label );
      }
      else {
         if ( $instruction->opcode != OP_NOP ) {
            UNREACHABLE( "unhandled instruction opcode: %d",
               $instruction->opcode );
         }
      }
   }

   private function visitBinaryInstruction(
      BinaryInstruction $instruction ): void {
      switch ( $instruction->opcode ) {
      case OP_ADD:
         $lside = $seq->getReg( $instruction->lside );
         $rside = $seq->getReg( $instruction->rside );
         $seq->addRR( OP_ADD_R64R64, $result, $rside );
         break;
      case OP_SUB:
         $result = $seq->allocReg( $instruction->result );
         $lside = $seq->getReg( $instruction->lside );
         $seq->addRR( OP_MOV_R64R64, $result, $lside );
         $rside = $seq->getReg( $instruction->rside );
         $seq->addRR( OP_SUB_RR, $result, $rside );
         break;
      default:
         UNREACHABLE();
      }
   }

   private function visitCall( Sequence $seq,
      Oatir\CallInstruction $instruction ): void {
      $walker = new CallWalker( $instruction );
      $walker->visit( $seq );
   }
}
