<?php

declare( strict_types = 1 );

namespace Codegen\X64;

class BlockDumper {
   private bool $showDefs;
   private bool $showLiveValues;
   private bool $showChildBlocks;
   private bool $showParentBlocks;
   /**
    * @param Block[] $blocks
    */
   public function __construct( private array $blocks ) {
      $this->showDefs = false;
      $this->showLiveValues = false;
      $this->showChildBlocks = false;
      $this->showParentBlocks = false;
   }

   public function showDefs(): void {
      $this->showDefs = true;
   }

   public function showLiveValues(): void {
      $this->showLiveValues = true;
   }

   public function showChildBlocks(): void {
      $this->showChildBlocks = true;
   }

   public function showParentBlocks(): void {
      $this->showParentBlocks = true;
   }

   public function dump(): void {
      $block = reset( $this->blocks );
      while ( $block !== null ) {
         $this->dumpBlock( $block );
         $block = $block->next;
      }
   }

   public function dumpBlock( Block $block ): void {
      $content = new Content();

      $this->dumpBlockLabel( $content, $block );
      $content->append( "\n" );
      $content->indent();
      foreach ( $block->instructions as $instruction ) {
         $this->dumpInstruction( $content, $instruction );
      }
      if ( $block->exitJump !== null ) {
         $this->dumpJump( $content, $block->exitJump );
      }

      if ( $this->showDefs && count( $block->defs ) > 0 ) {
         $content->appendln( '; defs:' );
         foreach ( $block->defs as $def ) {
            $content->append( ';   ' );
            $this->dumpValue( $content, $def );
            $content->append( "\n" );
         }
      }

      if ( $this->showLiveValues && count( $block->liveValues ) > 0 ) {
         $content->appendln( '; live values:' );
         foreach ( $block->liveValues as $value ) {
            $content->append( ';   ' );
            $this->dumpValue( $content, $value );
            $content->append( "\n" );
         }
      }

      if ( $this->showParentBlocks && count( $block->parents ) > 0 ) {
         $content->appendln( '; parent blocks:' );
         foreach ( $block->parents as $parent ) {
            $content->append( ';   ' );
            $this->dumpBlockLabel( $content, $parent );
            $content->append( "\n" );
         }
      }
      if ( $this->showChildBlocks && count( $block->children ) > 0 ) {
         $content->appendln( '; children blocks:' );
         foreach ( $block->children as $child ) {
            $content->append( ';   ' );
            $this->dumpBlockLabel( $content, $child );
            $content->append( "\n" );
         }
      }

      $content->dedent();
      $content->append( "\n" );

      printf( "%s", $content->output );
   }

   public function dumpBlockLabel( Content $content, Block $block ): void {
      $content->append( ".block%d", $block->id );
   }

   public function dumpInstruction( Content $content,
      Instruction $instruction ): void {
      if ( $instruction instanceof AddInstruction ) {
         $content->append( 'add ' );
         $this->dumpValue( $content, $instruction->dst );
         $content->append( ', ' );
         $this->dumpValue( $content, $instruction->src );
         $content->append( "\n" );
      }
      else if ( $instruction instanceof CmpInstruction ) {
         $content->append( 'cmp ' );
         $this->dumpValue( $content, $instruction->lside );
         $content->append( ', ' );
         $this->dumpValue( $content, $instruction->rside );
         $content->append( "\n" );
      }
      else if ( $instruction instanceof SetInstruction ) {
         switch ( $instruction->type ) {
         case SetInstruction::SET_Z:
            $content->append( 'setz' );
            break;
         case SetInstruction::SET_NZ:
            $content->append( 'setnz' );
            break;
         case SetInstruction::SET_L:
            $content->append( 'setl' );
            break;
         default:
            var_dump( $instruction );
            UNREACHABLE();
         }
         $content->append( ' ' );
         $this->dumpValue( $content, $instruction->result );
         $content->append( "\n" );
      }
      else if ( $instruction instanceof TestInstruction ) {
         $content->append( 'test ' );
         $this->dumpValue( $content, $instruction->lside );
         $content->append( ', ' );
         $this->dumpValue( $content, $instruction->rside );
         $content->append( "\n" );
      }
      else if ( $instruction instanceof MovInstruction ) {
         $content->append( 'mov ' );
         if ( $instruction->dst !== null ) {
            if ( is_int( $instruction->dst ) ) {
               $content->append( '%d', $instruction->dst );
            }
            else {
               $this->dumpValue( $content, $instruction->dst );
            }
         }
         $content->append( ', ' );
         if ( $instruction->src !== null ) {
            $this->dumpValue( $content, $instruction->src );
         }
         $content->append( "\n" );
      }
      else if ( $instruction instanceof MovSlotInstruction ) {
         $content->append( 'mov-slot ' );
         $content->append( 'slot' );
         $content->append( ', ' );
         $this->dumpValue( $content, $instruction->value );
         $content->append( "\n" );
      }
      else if ( $instruction instanceof MovRetInstruction ) {
         $content->append( 'mov ' );
         $content->append( 'ret-reg' );
         $content->append( ', ' );
         $this->dumpValue( $content, $instruction->value );
         $content->append( "\n" );
      }
      else {
         $content->append( $instruction->dump() . "\n" );
      }
   }

   public function dumpValue( Content $content, Value $value ): void {
      $content->append( 'val%d', $value->id );
      $params = [];
      if ( $value->name !== '' ) {
         $params[ 'name' ] = $value->name;
      }

      if ( $value->residence === RESIDENCE_IMM ) {
         $params[ 'immediate' ] = $value->immediate;
      }

      if ( ! empty( $params ) ) {
         $output = '';
         foreach ( $params as $k => $v ) {
            if ( $output !== '' ) {
               $output .= ', ';
            }
            $output .= sprintf( '%s=%s', $k, $v );
         }
         $content->append( ' (%s)', $output );
      }
   }

   public function dumpJump( Content $content, Jump $jump ): void {
      if ( $jump instanceof GotoJump ) {
         $content->append( 'jmp ' );
         $this->dumpBlockLabel( $content, $jump->dst );
         $content->append( "\n" );
      }
      else if ( $jump instanceof ConditionalJump ) {
         switch ( $jump->cond ) {
         case COND_JUMP_ZERO:
            $content->append( 'jz' );
            break;
         case COND_JUMP_NOT_ZERO:
            $content->append( 'jnz' );
            break;
         }
         $content->append( ' ' );
         $this->dumpBlockLabel( $content, $jump->dst );
         $content->append( "\n" );
      }
      else {
         var_dump( $jump );
         UNREACHABLE();
      }
   }
}
