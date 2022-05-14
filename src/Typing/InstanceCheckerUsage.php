<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\Value;

trait InstanceCheckerUsage {
   private InstanceChecker $instanceChecker;

   private function isInstanceOf( Value $instance, Type $type ): bool {
      return $this->instanceChecker->isInstanceOf( $instance, $type );
   }

   private function isEnumeratorOf( \Enumerator $target,
      \Enumeration $enumeration ): bool {
      foreach ( $enumeration->body as $enumerator ) {
         if ( $enumerator === $target ) {
            return true;
         }
      }
      return false;
   }

   private function isRefType( Type $type ): bool {
      if ( $type->spec == TYPESPEC_STRUCT ) {
         switch ( $type->structure->name ) {
         case 'Int':
         case 'Bool':
            break;
         default:
            return true;
         }
      }
      else if ( $type->spec == TYPESPEC_ENUM ) {
         return true;
      }
      return false;
   }

   public function sizeOfType( Type $type ): int {
      switch ( $this->describe( $type ) ) {
      case Description::INT:
      case Description::PTR:
         return 8;
      case Description::BOOL:
         return 1;
      case Description::STRUCT:
         return $type->structure->size;
      default:
         return 0;
      }
   }

   public function replaceRefinements( Type $type, Type $otherType ): void {
      foreach ( $otherType->refinements as $name => $value ) {
         $type->refinements[ $name ] = $value;
      }
   }

   /**
    * @return Type[]
    */
   public function getReturnOptions( Type $type ): array {
      $options = [];
      switch ( $this->describe( $type ) ) {
      case Description::ENUM:
         foreach ( $type->enumeration->body as $enumerator ) {
            if ( $enumerator->result !== null ) {
               $options[] = $enumerator->result->type;
            }
         }
         break;
      default:
         $options[] = $type;
         break;
      }
      return $options;
   }
}
