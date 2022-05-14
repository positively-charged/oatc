<?php

declare( strict_types = 1 );

namespace Codegen\X64;

/**
 * This class outputs the internal representation of the assembly code into
 * a text file. The text file is formatted for the YASM assembler.
 *
 * @package Codegen\X64
 */
class Writer {
   public function write( Assembly $assembly, string $outputPath ): void {
      $content = new Content();
      $this->writeAssembly( $content, $assembly );
      file_put_contents( $outputPath, $content->output );
      var_dump( $content->output );
   }

   private function writeAssembly( Content $content,
      Assembly $assembly ): void {
      $this->writeDataSection( $content, $assembly );
      $this->writeTextSection( $content, $assembly );
   }

   private function writeDataSection( Content $content,
      Assembly $assembly ): void {
      if ( count( $assembly->strings ) > 0 ) {
         $content->append( "section .data\n" );
         foreach ( $assembly->strings as $string ) {
            $value = explode( "\n", $string->value );
            $value = '"' . implode( '", 10, "', $value ) . '"';
            $content->append( "s%d db %s, 0\n", $string->index, $value );
         }
      }
   }

   private function writeTextSection( Content $content,
      Assembly $assembly ): void {
      $content->append( "section .text\n" );
      $this->writeGlobalEntries( $content, $assembly );
      foreach ( $assembly->funcs as $func ) {
         $this->writeFunc( $content, $func );
      }
   }

   private function writeGlobalEntries( Content $content,
      Assembly $assembly ): void {
      if ( $this->hasGlobalFuncs( $assembly ) ) {
         $content->append( "; Globals\n" );
         foreach ( $assembly->funcs as $func ) {
            if ( $func->global ) {
               $content->append( "global %s\n", $func->name );
            }
            else if ( $func->extern ) {
               $content->append( "extern %s\n", $func->name );
            }
         }
         $content->append( "\n" );
      }
   }

   private function hasGlobalFuncs( Assembly $assembly ): bool {
      foreach ( $assembly->funcs as $func ) {
         if ( $func->global || $func->extern ) {
            return true;
         }
      }
      return false;
   }

   private function writeFunc( Content $content, Func $func ): void {
      if ( ! empty( $func->blocks ) ) {
         $content->append( "$%s:\n", $func->name );
         $this->writeInstructionSequence( $content, $func );
         $content->append( "\n" );
      }
   }

   private function writeInstructionSequence( Content $content,
      Func $func ): void {
      $content->indent();

      // Prologue.
      $prologueAdded = false;
      if ( $func->localSize > 0 ) {
         $content->append( "push rbp\n" );
         $content->append( "mov rbp, rsp\n" );
         $content->append( "sub rsp, %d\n", $func->localSize );
         $content->append( "\n" );
         $prologueAdded = true;

         if ( count( $func->params ) > 0 ) {
            $content->append( "; Save arguments into memory\n" );
            foreach ( $func->params as $param ) {
               if ( ! $param->argOnStack ) {
                  $content->append( "mov [rsp+%d], %s", $param->slot->offset,
                     $this->getRegName( $param->reg ) );
                  $content->append( " ; `%s`\n", $param->name );
               }
            }
            $content->append( "\n" );
         }
      }

/*
      foreach ( $func->blocks as $block ) {
         $this->writeBlock( $content, $block );
      } */
      $block = $func->blocks[ 0 ];
      while ( $block !== null ) {
         $this->writeBlock( $content, $block );
         $block = $block->next;
      }

      /*
      $iter = new InstructionIterator( $func );
      while ( $iter->next() ) {
         $this->writeInstruction( $content, $iter->instruction );
      }*/

      // Epilogue.
      $content->append( ".ret:\n" );
      if ( $prologueAdded ) {
         $content->append( "mov rsp, rbp\n" );
         $content->append( "pop rbp\n" );
      }
      $content->append( "ret\n" );

      $content->dedent();
   }

   private function writeBlock( Content $content, Block $block ): void {
      $label = sprintf( "%s:", sprintf( '.b%s', $block->id ) );
      if ( $block->comment !== '' ) {
         $label .= ' ; ' . $block->comment;
      }
      $content->append( "%s\n", $label );
      foreach ( $block->instructions as $instruction ) {
         $this->writeInstruction( $content, $instruction );
      }
      if ( $block->exitJump !== null ) {
         $this->writeJump( $content, $block->exitJump );
      }
      $content->append( "\n" );
   }

   private function getValue( Value $value ): string {
      switch ( $value->residence ) {
      case RESIDENCE_REG:
         return self::getRegName( $value->reg );
      }
   }

   private function writeInstruction( Content $content,
      Instruction $instruction ): void {
      if ( $instruction instanceof DestroyInstruction ) {
         return;
      }
      if ( $instruction instanceof MovInstruction ) {
         $this->writeMovInstruction( $content, $instruction );
      }
      else if ( $instruction instanceof MovRIInstruction ) {
         $content->append( "mov %s, %d",
            $this->getRegName( $instruction->reg ),
            $instruction->immediate );
      }
      else if ( $instruction instanceof AddInstruction ) {
         $content->append( "add %s, %s",
            $this->getRegName( $instruction->dstReg ),
            $this->getRegName( $instruction->srcReg ) );
      }
      else if ( $instruction instanceof IDivInstruction ) {
         $content->append( "idiv %s",
            $this->getRegName( $instruction->divisor ) );
      }
      else if ( $instruction instanceof SetSlotInstruction ) {
         $this->writeSetSlotInstruction( $content, $instruction );
      }
      else if ( $instruction instanceof PushInstruction ) {
         $this->writePushInstruction( $content, $instruction );
      }
      else if ( $instruction instanceof BinaryInstruction ) {
         $this->writeBinaryInstruction( $content, $instruction );
      }
      else if ( $instruction instanceof CallInstruction ) {
         $this->writeCallInstruction( $content, $instruction );
      }
      else if ( $instruction instanceof RetInstruction ) {
         if ( $instruction->value !== null ) {
            $content->append( "mov rax, %s\n", $this->getRegName(
               $instruction->value->reg ) );
         }
         $content->append( "jmp .ret\n" );
      }
      /*
      else if ( $instruction instanceof MachineInstruction ) {

      } */
      else {
         $content->append( "%s", $instruction->dump() );
      }
      if ( $instruction->comment != '' ) {
         //$content->append( ' ; %s', $instruction->comment );
      }
      $content->append( "\n" );
   }

   private function writeMovInstruction( Content $content,
      MovInstruction $instruction ): void {
      switch ( $instruction->type ) {
      case MOV_IMM_TO_REG:
         $content->append( "mov %s, %d",
            $this->getRegName( $instruction->dst ),
            $instruction->value );
         break;
      case MOV_MEM_TO_REG:
         $content->append( "mov %s, [rsp+%d]",
            $this->getRegName( $instruction->dstReg ),
            $instruction->addr );
         break;
      case MOV_REG_TO_MEM:
         $content->append( "mov [rsp+%d], %s",
            $instruction->value,
            $this->getRegName( $instruction->dstReg ) );
         break;
      case MOV_REG_TO_REG:
         $content->append( "mov %s, %s",
            $this->getRegName( $instruction->dstReg ),
            $this->getRegName( $instruction->srcReg ) );
         break;
      default:
         UNREACHABLE();
      }
   }

   private function writeSetSlotInstruction( Content $content,
      SetSlotInstruction $instruction ): void {
      $content->append( "mov [rsp+%d], %s",
         $instruction->destination->offset,
         $this->getRegName( $instruction->sourceReg ) );
   }

   private function writePushInstruction( Content $content,
      PushInstruction $instruction ): void {
      $content->append( "push %s",
         $this->getRegName( $instruction->reg->reg ) );
   }

   private function writeBinaryInstruction( Content $content,
      BinaryInstruction $instruction ): void {
      switch ( $instruction->opcode ) {
      case OP_ADD:
         $content->append( "add %s, %s",
            $this->getRegName( $instruction->lsideReg ),
            $this->getRegName( $instruction->rsideReg ) );
         break;
      }
   }

   private function writeCallInstruction( Content $content,
      CallInstruction $instruction ): void {
      $content->append( "call %s\n", $instruction->func->name );
      if ( $instruction->returnValue !== null ) {
         $content->append( "mov %s, rax\n", $this->getRegName(
            $instruction->returnValue->reg ) );
      }
   }

   private function writeJump( Content $content, Jump $jump ): void {
      if ( $jump instanceof ReturnJump ) {
         $content->append( "ret\n" );
      }
      else if ( $jump instanceof ConditionalJump ) {
         $label = sprintf( '.b%s', $jump->dst->id );
         //$reg = $this->getRegName( $jump->condReg );
         //$content->append( "test %s, %s\n", $reg, $reg );
         switch ( $jump->cond ) {
         case COND_JUMP_ZERO:
            $content->append( "jz %s\n", $label );
            break;
         case COND_JUMP_NOT_ZERO:
            $content->append( "jnz %s\n", $label );
            break;
         }
         #$label = sprintf( '.b%s', $jump->onTrue->id );
         #$content->append( "jmp %s\n", $label );
         //$this->writeBlock( $content, $jump->onTrue );
         //$this->writeBlock( $content, $jump->onFalse, true );
         /*
            $reg = $this->getReg( $block->exitJump->cond );
            $seq->addRI( OP_TEST, $reg, $reg );
            $seq->addM( OP_JZ, $label );
            $label = sprintf( '.b%s', $block?->exitJump->onTrue->id );
            $seq->addM( OP_JMP, $label ); */
         #$this->writeBlock( $content, $block->exitJump->onTrue );
         #$this->writeBlock( $content, $block->exitJump->onFalse );
      }
      else {
         $label = sprintf( '.b%s', $jump->dst->id );
         $content->append( "jmp %s\n", $label );
      }
      /*
         if ( $block->exitJump->cond !== null ) {
            $label = sprintf( '.b%s', $block->exitJump->onFalse->id );
            $reg = $seq->getReg( $block->exitJump->cond );
            $seq->addRI( OP_TEST, $reg, $reg );
            $seq->addM( OP_JZ, $label );
            $label = sprintf( '.b%s', $block?->exitJump->onTrue->id );
            $seq->addM( OP_JMP, $label );
         }
         else {
            $label = sprintf( '.b%s', $block?->exitJump->dst->id );
            $seq->addM( OP_JMP, $label );
         }*/
   }

   public static function getRegName( int $reg ): string {
      return Instruction::getRegName( $reg );
   }
}

