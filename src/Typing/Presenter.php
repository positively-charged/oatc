<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\Value;
use Task;
use Typing\Description as Desc;

/**
 * Creates a textual representation of a type.
 */
class Presenter {
   public function __construct(
      private Task $task,
      private Describer $typeDescriber,
   ) {}

   /**
    * @param Type[] $args
    */
   public function presentType( Type $type, array $args = [] ): string {
      $text = $this->presentTypeStart( $type, $args );

/*
      if ( $type->refinements ) {
      var_dump( $type->refinements );
         foreach ( $type->refinements as $refinement ) {
            $text .= sprintf( '? %s( %s )', $refinement->target,
               $this->presentType( $refinement->type ) );
            if ( $refinement->value !== null ) {
               $text .= sprintf( ' on %s', $this->presentType(
                  $refinement->value->type ) );
            }
         }
      }
*/

      return $text;
   }

   private function presentTypeStart( Type $type, array $args = [] ): string {
      $text = '';
      if ( $type->borrowed ) {
         $text .= '&';
      }
      if ( $type->mutable ) {
         $text .= 'mut ';
      }
      return $text . $this->presentTypeBody( $type, $args );
   }

   private function presentTypeBody( Type $type, array $args = [] ): string {
      switch ( $this->typeDescriber->describe( $type ) ) {
      case Desc::ENUM:
         return $this->presentEnumeration( $type->enumeration );
      case Desc::INT:
      case Desc::UNCHECKED_INT:
         $text = '';
         if ( $type->unchecked ) {
            $text .= 'unchecked ';
         }
         if ( $type->value !== null ) {
            $text .= sprintf( '%d', $type->value );
         }
         else {
            $text .= 'Int';
         }
         return $text;
      case Desc::INT64:
         return '__PrimitiveInt64';
      case Desc::BOOL:
         if ( $type->value !== null ) {
            return sprintf( '%s', $type->value !== 0 ? 'true' : 'false' );
         }
         return 'Bool';
      case Desc::STR:
         if ( $type->value !== null ) {
            if ( array_key_exists( $type->value, $this->task->strings ) ) {
               return sprintf( '"%s"', $this->task->strings[ $type->value ] );
            }
            else {
               return '"?"';
            }
         }
         return 'Str';
      case Desc::PTR:
      case Desc::STRUCT:
         return $this->presentStructInstance( $type );
      case Desc::STRUCT_TYPE:
         return $this->presentStructType( $type );
      case Desc::VOID:
         return 'struct()';
      case Desc::PLACEHOLDER:
         return $this->presentPlaceholder( $type, $args );
      case Desc::NEVER:
         return $type->structure->name;
      case Desc::ERR:
         return 'Err';
      case Desc::VALUE:
         return $this->presentItemType( $type );
      case Desc::BINDING:
         return $this->presentBindingType( $type );
      case Desc::TYPE:
         return 'Type';
      default:
         var_dump( $this->typeDescriber->describe( $type ) );
         throw new \Exception();
         return '';
      }
   }

   public function presentEnumeration( \Enumeration $enumeration ): string {
      if ( empty( $enumeration->name ) ) {
         $text = '';
         foreach ( $enumeration->body as $enumerator ) {
            if ( $text !== '' ) {
               $text .= '|';
            }
            $text .= $this->presentType( $enumerator->type );
            //$text .= $enumerator->name;
         }
         return $text;
      }
      else {
         return $enumeration->name;
      }
   }

   public function presentStructInstance( Type $type ): string {
      $text = '';
      if ( $type->value !== null ) {
         $text .= '$param';
      }
      return $text . $this->presentStruct( $type->structure, $type->args,
            $type->refinements );
   }

   /**
    * @param Type[] $args
    * @param Value[] $refinements
    */
   public function presentStruct( \Structure $structure, array $args = [],
      array $refinements = [] ): string {
      $text = $structure->name;
      if ( $structure->name === '' ) {
         $text = 'struct';
      }

      if ( count( $structure->params ) > 0 ) {
         $text .= '[ ';
         $params = '';
         if ( count( $args ) > 0 ) {
            foreach ( $args as $arg ) {
               if ( $params !== '' ) {
                  $params .= ', ';
               }
               $params .= $this->presentType( $arg );
            }
         }
         else {
            foreach ( $structure->params as $param ) {
               if ( $params !== '' ) {
                  $params .= ', ';
               }
               $params .= $param->name;
            }
         }

         $text .= $params . ' ]';
      }

      if ( count( $structure->members ) > 0 ) {
         $text .= '( ';
         $count = 0;
         foreach ( $structure->members as $member ) {
            if ( $count > 0 ) {
               $text .= ', ';
            }

            if ( $member->name !== '' ) {
               $text .= sprintf( '%s: ', $member->name );
            }

            if ( array_key_exists( $member->name, $refinements ) ) {
               $text .= sprintf( '%s',
                  $this->presentType( $refinements[ $member->name ]->type ) );
            }
            else {
               $text .= sprintf( '%s',
                  $this->presentType( $member->type ) );
            }
            ++$count;
         }
         $text .= ' )';
      }
      else {
         if ( $structure->name === '' ) {
            $text .= '()';
         }
      }
      return $text;
   }

   public function presentStructType( Type $type ): string {
      $text = '';


      if ( $type->borrowed ) {
         $text .= '&';
      }
      if ( $type->mutable ) {
         $text .= 'mut ';
      }

      $text .= $type->structure->name;
      if ( $type->structure->name === '' ) {
         $text = 'struct';
      }

      if ( count( $type->structure->params ) > 0 ) {
         $text .= '[ ';
         $params = '';
         if ( count( $type->args ) > 0 ) {
            foreach ( $type->args as $arg ) {
               if ( $params !== '' ) {
                  $params .= ', ';
               }
               $params .= $this->presentType( $arg );
            }
         }
         else {
            foreach ( $type->structure->params as $param ) {
               if ( $params !== '' ) {
                  $params .= ', ';
               }
               $params .= $param->name;
            }
         }

         $text .= $params . ' ]';
      }

      if ( count( $type->structure->members ) > 0 ) {
         $text .= '( ';
         $count = 0;
         foreach ( $type->structure->members as $member ) {
            if ( $count > 0 ) {
               $text .= ', ';
            }
            $text .= sprintf( '%s: %s', $member->name,
               $this->presentType( $member->type, $type->args ) );
            ++$count;
         }
         $text .= ' )';
      }
      else {
         if ( $type->structure->name === '' ) {
            $text .= '()';
         }
      }

      $text = sprintf( 'Struct[ %s ]', $text );

      return $text;
   }

   /**
    * @param Type[] $args
    */
   public function presentPlaceholder( Type $type, array $args = [] ): string {
      if ( array_key_exists( $type->typeParam->argPos, $args ) ) {
         return $this->presentType( $args[ $type->typeParam->argPos ] );
      }
      return $type->typeParam->name;
   }

   private function presentItemType( Type $type ): string {
      $text = sprintf( '%s', $type->name );
      return $text;
      $object = $type->value;
      if ( $object instanceof Value ) {
         $object = $object->binding->node;
      }

      if ( $object instanceof \Constant ) {
         return sprintf( '%s (a constant)', $object->name );
      }
      else if ( $object instanceof \Let ) {
         return sprintf( '%s', $object->name );
      }
      else if ( $object instanceof \Param ) {
         return sprintf( '%s', $object->name );
      }
      else {
         UNREACHABLE();
      }
   }

   private function presentBindingType( Type $type ): string {
      $text = sprintf( '%s', $type->binding->name );
      if ( $type->binding->node instanceof \Constant ) {
         $text .= '(a constant)';
      }
      return $text;
   }
}
