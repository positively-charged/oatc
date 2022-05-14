<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\Value;

const DESC_INT = 2;
const DESC_STR = 3;
const DESC_BOOL = 4;

class Describer {
   public function describeValue( Value $value ): Description {
      switch ( $this->describe( $value->type ) ) {
      //case Description::STRUCT_TYPE:
      //case Description::ENUM_TYPE:
      //   return Description::TYPE;
      default:
         return $this->describe( $value->type );
      }
   }

   public function describe( Type $type ): Description {
      if ( $type->placeholder ) {
         return Description::PLACEHOLDER;
      }

      if ( $type->borrowed ) {
         if ( $type->structure !== null ) {

         }
      }

      switch ( $type->spec ) {
      case TYPESPEC_ENUM:
         return Description::ENUM;
      case TYPESPEC_STRUCT:
         switch ( $type->structure->builtin ) {
         case BUILTIN_STRUCTURE_INT:
            if ( $type->unchecked ) {
               return Description::UNCHECKED_INT;
            }
            return Description::INT;
         case BUILTIN_STRUCTURE_BOOL:
            return Description::BOOL;
         case BUILTIN_STRUCTURE_STR:
            return Description::STR;
         case BUILTIN_STRUCTURE_PTR:
         case BUILTIN_STRUCTURE_CONST_PTR:
            return Description::PTR;
         case BUILTIN_STRUCTURE_VEC:
            break;
         case BUILTIN_STRUCTURE_TYPE:
            return Description::STRUCT;
            //return Description::TYPE;
         default:
            // HACK: remove.
            if ( $type->structure->name === 'Vec' ) {
               break;
            }
            if ( count( $type->structure->members ) === 0 ) {
               return Description::VOID;
            }
            break;
         }
         return Description::STRUCT;
      case TYPESPEC_TRAIT:
         return Description::TRAIT;
      case TYPESPEC_STRUCT_TYPE:
         switch ( $type->structure->builtin ) {
         case BUILTIN_STRUCTURE_NEVER:
            return Description::NEVER;
         case BUILTIN_STRUCTURE_TYPE:
            return Description::TYPE;
         default:
            break;
         }
         return Description::STRUCT_TYPE;
      case TYPESPEC_STRUCT_INFO:
         return Description::STRUCT_INFO;
      case TYPESPEC_ENUM_TYPE:
         return Description::ENUM_TYPE;
      case TYPESPEC_ERR:
         return Description::ERR;
      case TYPESPEC_VALUE:
         return Description::VALUE;
      case TYPESPEC_BINDING:
         return Description::BINDING;
      case TYPESPEC_GENERIC:
         return Description::GENERIC;
      case TYPESPEC_TYPE:
         return Description::TYPE;
      case TYPESPEC_INT64:
         return Description::INT64;
      case TYPESPEC_VOID:
         return Description::VOID;
      default:
      var_dump( $type->spec );
         UNREACHABLE( 'unhandled type specifier: %d', $type->spec );
      }
   }
}
