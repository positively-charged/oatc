<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

/**
 * The StructSet is used to create a unique set of C structs. Any duplicates
 * that are added to the set will be removed; that is, any structs with
 * matching layout will be treated as a single struct.
 */
class StructSet {
   /** @var CStruct[] */
   private array $structs;

   public function __construct() {
      $this->structs = [];
   }

   public function add( CStruct $struct ): CStruct {
      foreach ( $this->structs as $storedStruct ) {
         if ( $this->isLayoutCompatible( $struct, $storedStruct ) ) {
            return $storedStruct;
         }
      }

      $struct->index = count( $this->structs );
      $this->structs[] = $struct;

      return $struct;
   }


   private function isLayoutCompatible( CStruct $a, CStruct $b ): bool {
      if ( $a->union !== $b->union ) {
         return false;
      }

      if ( count( $a->members ) === count( $b->members ) ) {
         for ( $i = 0; $i < count( $a->members ); ++$i ) {
            $memberA = $a->members[ $i ];
            $memberB = $b->members[ $i ];
            if ( ! $this->isSameType( $memberA->type, $memberB->type ) ) {
               return false;
            }

            if ( $memberA->dims !== $memberB->dims ) {
               return false;
            }
         }

         return true;
      }

      return false;
   }

   private function isSameType( CType $a, CType $b ): bool {
      if ( $a->spec !== $b->spec ) {
         return false;
      }

      if ( $a->struct !== null ) {
         if ( $b->struct === null ||
            ! $this->isLayoutCompatible( $a->struct, $b->struct ) ) {
            return false;
         }
      }

      return true;
   }

   /**
    * @return CStruct
    */
   public function get( int $index ): CStruct {
      if ( array_key_exists( $index, $this->structs ) ) {
         return $this->structs[ $index ];
      }
      throw new \Exception();
   }

   /**
    * @return CStruct[]
    */
   public function getAll(): array {
      return $this->structs;
   }
}
