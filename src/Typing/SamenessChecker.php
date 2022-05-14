<?php

declare( strict_types = 1 );
namespace Typing;

use Checking\BuiltinModule;
use Checking\Value;
use Typing\Description as Desc;

class SamenessChecker {
   use DescriberUsage;

   public function __construct(
      private BuiltinModule $builtinModule,
      private Describer $typeDescriber ) {}


   public function isCompatibleType( Type $candidate,
      Type $requirement ): bool {

   }

   public function isSameType( Type $candidate, Type $requirement ): bool {
      switch ( $this->describe( $requirement ) ) {
      case DESC::ENUM:
         return $this->isCompatibleEnumType( $candidate,
            $requirement->enumeration );
      case DESC::STRUCT:
         if ( count( $requirement->structure->members ) === 1 &&
            $requirement->structure->members[ 0 ]->name === '' ) {
            return $this->isSameType( $candidate,
               $requirement->structure->members[ 0 ]->type );
         }

         return ( $this->describe( $candidate ) == DESC::STRUCT &&
            $this->isSameStructType( $candidate, $requirement->structure,
               $requirement->refinements, $requirement->args ) && (
               $requirement->value === null ||
                  $requirement->value === $candidate->value ) &&
            $this->isCompatibleRefs( $requirement, $candidate ) );
      case DESC::STRUCT_TYPE:
         return $this->isCompatibleStructStructType( $candidate, $requirement,
            $requirement->args );
      case DESC::INT:
      case DESC::BOOL:
      case DESC::STR:
         /*
            if ( $this->describe( $instance ) === DESC::ENUM ) {
               return $this->isInstanceOf( $instance->enumeration->baseType,
                  $type );
            }
         */

         if ( $candidate->borrowed !== $requirement->borrowed ) {
            return false;
         }

         if ( $requirement->borrowed && $requirement->mutable &&
            ! $candidate->mutable ) {
            return false;
         }

         //$instance = $instance->reveal();
         return ( $this->describe( $candidate ) === $this->describe( $requirement ) &&
            $candidate->value === $requirement->value );
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
         return $this->isCompatibleValueType( $candidate, $requirement );
      case Desc::BINDING:
         return ( $instance->binding === $type->binding );
      case Desc::UNCHECKED_INT:
         return ( $this->describe( $instance->type ) === Desc::UNCHECKED_INT );
      case Desc::TYPE:
         return $this->isInstanceOfType( $instance, $type );
      case Desc::INT64:
         return ( $this->describe( $instance->type ) === DESC::INT );
      default:
         var_dump( $this->describe( $requirement ) );
         throw new \Exception();
      }
   }

   /**
    * @param Value[] $refinements
    * @param Type[] $args
    */
   public function isSameStructType( Type $candidate, \Structure $structure,
      array $refinements = [], array $args = [] ): bool {
      // If the type we are assigning to is an unnamed struct, we use
      // structural typing.
      if ( $structure->name === '' || $structure->trait ) {
         return $this->isStructurallyCompatible( $candidate->structure,
            $structure );
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
         return $this->isSameRefinedStructType( $candidate, $structure,
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
            if ( $instanceMember !== null && $this->isSameType(
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
   private function isSameRefinedStructType( Type $candidate,
      \Structure $structure, array $refinements = [],
      array $args = [] ): bool {
      $count = 0;
      while ( $count < count( $args ) &&
         $count < count( $candidate->args ) ) {
         if ( ! $this->isSameType( $candidate->args[ $count ],
            $args[ $count ] ) ) {
            return false;
         }
         ++$count;
      }

      foreach ( $refinements as $name => $expectation ) {
         // Instance must have a refinement consistent with the type.
         if ( ! array_key_exists( $name, $candidate->refinements ) ) {
            return false;
         }
         $refinement = $candidate->refinements[ $name ];
         if ( $this->describe( $expectation->type ) === DESC::STRUCT_TYPE ) {
            if ( ! $this->isSameStructType( $refinement->type, $structure,
               $refinements ) ) {
               return false;
            }
         }
         else {
            if ( ! $this->isSameType( $refinement->type,
               $expectation->type ) ) {
               return false;
            }
         }
      }

      return ( $candidate->structure === $structure );
   }

   private function isCompatibleValueType( Type $candidate,
      Type $requirement ): bool {
      return ( $this->describe( $candidate ) === Desc::VALUE &&
         $candidate->value === $requirement->value );
   }
}
