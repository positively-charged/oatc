<?php

declare( strict_types = 1 );

namespace Typing;

trait PresenterUsage {
   private Presenter $typePresenter;

   private function presentType( Type $type ): string {
      return $this->typePresenter->presentType( $type );
   }
}
