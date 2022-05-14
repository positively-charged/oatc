<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\Value;

class AnonymousUnion {
   use DescriberUsage;

   /** @var Value[] */
   private array $members;

   public function __construct(
      private SamenessChecker $samenessChecker,
      private Describer $typeDescriber ) {
      $this->members = [];
   }

   public function addMember( Type $newMember ): void {
      if ( $this->describe( $newMember ) === Description::ENUM ) {
         foreach ( $newMember->enumeration->body as $enumerator ) {
            $this->addMember( $enumerator->type );
         }
      }
      else {
         foreach ( $this->members as $member ) {
            if ( $this->samenessChecker->isSameType( $newMember, $member ) ) {
               return;
            }
         }
         $this->members[] = $newMember;
      }
   }

   public function createType(): Type {
      if ( count( $this->members ) === 1 ) {
         return $this->members[ 0 ];
      }
      else {
         $enumeration = new \Enumeration();
         $enumeration->name = '';
         foreach ( $this->members as $member ) {
            $enumerator = new \Enumerator();
            //$enumerator->name = $this->determineEnumeratorName( $member );
            //$enumerator->result = $member;
            $enumerator->type = $member;
            $enumeration->body[] = $enumerator;
         }

         $type = new Type();
         $type->enumeration = $enumeration;
         $type->spec = TYPESPEC_ENUM;


         return $type;
      }
   }

   private function determineEnumeratorName( Value $member ): string {
      if ( $member->type->spec === TYPESPEC_VALUE ) {
         $object = $member->inhabitant;
         if ( $object instanceof Value ) {
            $object = $object->binding->node;
         }
         if ( $object instanceof \Constant ||
            $object instanceof \Let ) {
            return $object->name;
         }
         else {
            var_dump( $object );
            UNREACHABLE();
         }
      }
      else if ( $member->type->spec === TYPESPEC_STRUCT_TYPE ||
         $member->type->spec === TYPESPEC_STRUCT ) {
         return $member->structure->name;
      }
      else {
         var_dump( $member );
         UNREACHABLE();
      }
   }
}
