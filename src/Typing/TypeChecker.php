<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\BuiltinModule;
use Typing\Description as Desc;

class TypeChecker {
   use DescriberUsage;

   public function __construct(
      private BuiltinModule $builtinModule,
      private Describer $typeDescriber ) {}

   public function isSameType( Type $candidate, Type $requirement ): bool {

   }

   public function isCompatibleType( Type $candidate,
      Type $requirement ): bool {

      switch ( $this->describe( $requirement ) ) {
      case DESC::ENUM:
         return $this->isCompatibleEnumType( $instance, $type->enumeration );
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

   public function getIntStruct(): \Structure {
      return $this->builtinModule->intStruct;
   }

   public function getBoolStruct(): \Structure {
      return $this->builtinModule->boolStruct;
   }

   public function getStrStruct(): \Structure {
      return $this->builtinModule->strStruct;
   }

   public function createIntType(): Type {
      $type = new Type();
      $type->structure = $this->getIntStruct();
      $type->spec = TYPESPEC_STRUCT;
      #$type->borrowed = true;
      return $type;
   }

   public function createBoolType(): Type {
      $type = new Type();
      $type->structure = $this->getBoolStruct();
      $type->spec = TYPESPEC_STRUCT;
      #$type->borrowed = true;
      return $type;
   }

   public function createStrType(): Type {
      $type = new Type();
      $type->structure = $this->getStrStruct();
      $type->spec = TYPESPEC_STRUCT;
      #$type->borrowed = true;
      return $type;
   }

   public function createVoidType(): Type {
      return new Type();
   }
}
