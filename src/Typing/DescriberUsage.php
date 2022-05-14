<?php

declare( strict_types = 1 );

namespace Typing;

use Checking\Value;

trait DescriberUsage {
   private Describer $typeDescriber;

   private function describe( Type $type ): Description {
      return $this->typeDescriber->describe( $type );
   }

   private function describeValue( Value $value ): Description {
      return $this->typeDescriber->describeValue( $value );
   }

   private function isVoid( Type $type ): bool {
      return $this->describe( $type ) === Description::VOID;
   }

   private function isZeroSizedStruct( \Structure $structure ): bool {
      return ( ! $structure->defined || ( count( $structure->members ) === 0 &&
         $structure->builtin === BUILTIN_STRUCTURE_NONE ) );
   }

   private function isOwned( Type $type ): bool {
      return ( $type->borrowed === false );
   }
}
