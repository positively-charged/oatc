<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

require_once CODEGEN_DIR . '/CAst/nodes.php';
require_once CODEGEN_DIR . '/CAst/scope.php';
require_once CODEGEN_DIR . '/CAst/CContent.php';
require_once CODEGEN_DIR . '/CAst/Writer.php';
require_once CODEGEN_DIR . '/CAst/ModuleWalk.php';
require_once CODEGEN_DIR . '/CAst/ExprWalker.php';
require_once CODEGEN_DIR . '/CAst/SimpleExprWalker.php';
require_once CODEGEN_DIR . '/CAst/CallWalker.php';
require_once CODEGEN_DIR . '/CAst/StackFrame.php';
require_once CODEGEN_DIR . '/CAst/StructSet.php';

use \Module;
use Typing\Describer;
use Typing\InstanceChecker;
use Typing\Presenter;

class CodegenTask {
   public array $funcsToCfuncs = [];
}

class CodeGenerator {
   private Module $module;
   private CodegenTask $codegenTask;

   public function __construct(
      private Describer $typeDescriber,
      private InstanceChecker $instanceChecker,
      private Presenter $presenter,
      private \Task $task, Module $module ) {
      $this->module = $module;
      $this->codegenTask = new CodegenTask();
   }

   public function publish( string $outputFile ): void {
      $unit = new CTranslationUnit();
      $walk = new ModuleWalk( $this->typeDescriber,
         $this->instanceChecker,
         $this->presenter, $this->task, $this->module,
         $this->codegenTask, $unit );
      $walk->visitModule();
      $writer = new Writer;
      $writer->write( $unit, $outputFile );
   }
}
