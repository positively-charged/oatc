<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\Value;
use Enumerator;
use Typing\Description as Desc;

class InstanceChecker {
   use DescriberUsage;

   public function __construct(
      private Describer $typeDescriber,
      private SamenessChecker $samenessChecker,
   ) {}

   public function isInstanceOf( Value $instance, Type $type ): bool {
      switch ( $this->describe( $type ) ) {
      case DESC::ENUM:
         return $this->isInstanceOfEnum( $instance, $type->enumeration );
      case DESC::STRUCT:
         /*
         if ( $this->describe( $instance ) === DESC::ENUM ) {
            return $this->isInstanceOf( $instance->enumeration->baseType,
               $type );
         }
         */

         if ( count( $type->structure->members ) === 1 &&
            $type->structure->members[ 0 ]->name === '' ) {
            return $this->isInstanceOf( $instance,
               $type->structure->members[ 0 ]->type );
         }

         return ( $this->describe( $instance->type ) == DESC::STRUCT &&
            $this->isInstanceOfStruct( $instance, $type->structure,
               $type->refinements, $type->args ) && (
               $type->value === null || $type->value === $instance->type->value ) &&
            $this->isCompatibleRefs( $type, $instance->type ) );
      case DESC::STRUCT_TYPE:
         return $this->isInstanceOfStructType( $instance, $type, $type->args );
      case DESC::INT:
      case DESC::BOOL:
      case DESC::STR:
      /*
         if ( $this->describe( $instance ) === DESC::ENUM ) {
            return $this->isInstanceOf( $instance->enumeration->baseType,
               $type );
         }
      */

         if ( $instance->borrowed !== $type->borrowed ) {
            return false;
         }

         if ( $type->borrowed && $type->mutable && ! $instance->type->mutable ) {
            return false;
         }

         //$instance = $instance->reveal();
         return ( $this->describe( $instance->type ) === $this->describe( $type ) &&
            ( $type->value === null || $instance->type->value === $type->value ) );
      case DESC::PTR:
         return $this->isInstanceOfStruct( $instance, $type->structure, [],
            $type->args );
      case DESC::TRAIT:
         return $this->isInstanceOfTrait( $instance, $type );
      case DESC::PLACEHOLDER:
         return $this->isInstanceOfPlaceholder( $instance, $type, $type->args );
      case DESC::VOID:
         return $this->isVoid( $instance->type );
         // Nothing can be assigned to the Never type; it has no values.
      case DESC::NEVER:
         return false;
      case Desc::VALUE:
         return $this->isInstanceOfItem( $instance, $type );
      case Desc::BINDING:
         return ( $instance->binding === $type->binding );
      case Desc::UNCHECKED_INT:
         return ( $this->describe( $instance->type ) === Desc::UNCHECKED_INT );
      case Desc::TYPE:
         return $this->isInstanceOfType( $instance, $type );
      case Desc::INT64:
         return ( $this->describe( $instance->type ) === DESC::INT );
      default:
      var_dump( $this->describe( $type ) );
         throw new \Exception();

         if ( $this->describe( $type ) === DESC::INT ) {
            return ( $this->isInstanceOfStruct( $instance,
                  $type->structure, $type->refinements ) && ( $type->value === null ||
                  $type->value === $instance->value ) );
         }
         else if ( $this->describe( $type ) === DESC::BOOL ) {
            return ( $this->isInstanceOfStruct( $instance,
                  $this->getBoolStruct() ) && ( $type->value === null ||
                  $type->value === $instance->value ) );
         }

         //return ( $this->describe( $instance ) == $this->describe( $type ) );
      }

      return false;
   }

   private function isInstanceOfEnum( Value $instance,
      \Enumeration $enumeration ): bool {
      if ( $this->describe( $instance->type ) === DESC::ENUM ) {
         if ( empty( $enumeration->name ) ) {
            foreach ( $instance->type->enumeration->body as $enumerator ) {
               $requiredEnumerator = $enumeration
                  ->findEnumerator( $enumerator->name );
               if ( $requiredEnumerator === null ) {
                  return false;
               }
            }
            return true;
         }
      }
      else {
         if ( empty( $enumeration->name ) ) {
            foreach ( $enumeration->body as $enumerator ) {
               if ( $this->isInstanceOfEnumerator( $instance, $enumerator ) ) {
                  return true;
               }
            }
            return false;
         }
      }
      return ( $this->describe( $instance->type ) == DESC::ENUM &&
         $instance->type->enumeration === $enumeration );
   }

   public function isInstanceOfEnumerator( Value $instance,
      Enumerator $enumerator ): bool {
      return $this->isInstanceOf( $instance, $enumerator->type );
   }

   /**
    * @param Value[] $refinements
    * @param Type[] $args
    */
   private function isInstanceOfStruct( Value $instance, \Structure $structure,
      array $refinements = [], array $args = [] ): bool {
      // If the type we are assigning to is an unnamed struct, we use
      // structural typing.
      if ( $structure->name === '' || $structure->trait ) {
         return $this->samenessChecker->isSameStructType( $instance->type,
            $structure, $refinements, $args );
      }
      /*
      else if ( in_array( $structure->name, [ 'Int', 'Bool', ] ) ) {
         if ( ! in_array( $instance->structure->name, [ 'Int', 'Bool' ] ) ||
            $structure->name !== $instance->structure->name ) {
            return false;
         }
         if ( array_key_exists( 'value', $refinements ) ) {
            if ( ! array_key_exists( 'value', $instance->refinements ) ) {
               return false;
            }
            if ( $instance->refinements[ 'value' ]->value !==
               $refinements[ 'value' ]->value ) {
               return false;
            }
         }
         return true;
      }*/
      else {
         return $this->isInstanceOfRefinedStruct( $instance, $structure,
            $refinements, $args );
      }
   }

   private function isStructurallyCompatible( \Structure $instance,
      \Structure $structure ): bool {
      if ( count( $instance->members ) === count( $structure->members ) ) {
         $memberCount = 0;
         foreach ( $structure->members as $member ) {
            if ( $memberCount >= count( $instance->members ) ) {
               break;
            }
            $instanceMember = $instance->members[ $memberCount ];
            if ( $instanceMember->name !== $member->name ) {
               return false;
            }
            if ( $instanceMember !== null && $this->isInstanceOf(
                  $instanceMember->type, $member->type ) ) {
               ++$memberCount;
            }
            else {
               return false;
            }
         }

         return true;
      }

      return false;
   }

   /**
    * @param Value[] $refinements
    * @param Type[] $args
    */
   private function isInstanceOfRefinedStruct( Value $instance,
      \Structure $structure, array $refinements = [],
      array $args = [] ): bool {
      $count = 0;
      while ( $count < count( $args ) &&
         $count < count( $instance->type->args ) ) {
         if ( ! $this->isInstanceOf( $instance->type->args[ $count ],
            $args[ $count ] ) ) {
            return false;
         }
         ++$count;
      }

      foreach ( $refinements as $name => $expectation ) {
         // Instance must have a refinement consistent with the type.
         if ( ! array_key_exists( $name, $instance->type->refinements ) ) {
            return false;
         }
         $refinement = $instance->type->refinements[ $name ];
         if ( $this->describe( $expectation->type ) === DESC::STRUCT_TYPE ) {
            if ( ! $this->isInstanceOfStruct( $refinement->type, $structure,
               $refinements ) ) {
               return false;
            }
         }
         else {
            if ( ! $this->isInstanceOf( $refinement->type,
               $expectation->type ) ) {
               return false;
            }
         }
      }

      return ( $instance->type->structure === $structure );
   }

   private function isInstanceOfPrimitiveStruct( Type $instance,
      Type $type ): bool {
      return $instance->spec === $type->spec;
      return ( $instance->convertToPrimitive( $type ) );
      if ( array_key_exists( 'value', $type->refinements ) ) {
         if ( ! array_key_exists( 'value', $instance->refinements ) ) {
            return false;
         }
         $refinement = $instance->refinements[ 'value' ];
         /*
         if ( ! $this->isInstanceOf( $refinement-> ) ) {
            return false;
         }*/
      }
      return $instance->structure === $type->structure;
   }

   /**
    * @param Type[] $args
    */
   private function isInstanceOfStructType( Value $instance,
      Type $type, array $args = [] ): bool {
      return ( $this->describe( $instance->type ) === Description::STRUCT_TYPE &&
         $this->isStructurallyCompatible( $instance->structure,
            $type->structure) );
      return $this->isInstanceOfStruct( $instance, $type->structure,
            $type->refinements, $args ) &&   (
            $type->value === null || $type->value === $instance->value ) &&
         $this->isCompatibleRefs( $type, $instance );
   }

   private function isCompatibleRefs( Type $type, Type $instance ): bool {
      if ( $type->borrowed === $instance->borrowed ) {
         if ( $type->mutable && ! $instance->mutable ) {
            return false;
         }
         return true;
      }
      return false;
   }

   private function isInstanceOfTrait( Value $instance, Type $type ): bool {
      if ( $this->describe( $instance->type ) == DESC::STRUCT ) {
         $impl = $this->findImpl( $instance->type, $type->trait->name );
         return ( $impl != null );
      }
      return false;
   }

   private function findImpl( Type $instance, string $name ): ?\Implementation {
      foreach ( $instance->structure->impls as $impl ) {
         if ( $impl->traitName == $name ) {
            return $impl;
         }
      }
      return null;
   }

   /**
    * @param Type[] $args
    */
   private function isInstanceOfPlaceholder( Value $instance,
      Type $type, array $args = [] ): bool {
      if ( array_key_exists( $type->typeParam->argPos, $args ) ) {
         return $this->isInstanceOf( $instance,
            $args[ $type->typeParam->argPos ] );
      }
      else {
         throw new \Exception( 'missing type argument for type parameter `' .
            $type->typeParam->name . '`' );
         // Make sure the current type is compatible with a previous type for
         // the same generic parameter.
         //if ( $this->instanceOf( $this->su
      }
   }

   private function describe( Type $type ): Description {
      return $this->typeDescriber->describe( $type );
   }


   private function isInstanceOfItem( Value $instance, Type $type ): bool {
      return ( $instance === $type->value );
   }

   public function findEnumerator( \Enumeration $enumeration,
      Type $instance ): ?Enumerator {
      foreach ( $enumeration->body as $enumerator ) {
         if ( $this->isInstanceOfEnumerator( $instance, $enumerator ) ) {
            return $enumerator;
         }
      }
      return null;
   }

   private function isInstanceOfType( Value $instance, Type $type ): bool {
      if ( $this->describe( $instance->type ) === Desc::TYPE ) {
         return true;
      }
      return false;
   }

}
