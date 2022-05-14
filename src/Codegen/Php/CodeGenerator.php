<?php

declare( strict_types = 1 );

namespace Codegen\Php;

use Typing\TypeChecker;

require_once CODEGEN_DIR . '/Php/nodes.php';
require_once CODEGEN_DIR . '/Php/scope.php';
require_once CODEGEN_DIR . '/Php/Result.php';
require_once CODEGEN_DIR . '/Php/ModuleWalker.php';
require_once CODEGEN_DIR . '/Php/ExprWalker.php';
require_once CODEGEN_DIR . '/Php/Content.php';
require_once CODEGEN_DIR . '/Php/Writer.php';
/*
require_once './scope.php';
require_once './CContent.php';
require_once './Writer.php';
require_once './StmtWalk.php';
require_once './ExprWalker.php';
*/

class CodeGenerator {
   private \Task $task;
   private \Module $module;
   private \Typing\TypeChecker $typeChecker;

   public function __construct( \Task $task, TypeChecker $typeChecker ) {
      $this->task = $task;
      $this->module = $task->module;
      $this->typeChecker = $typeChecker;
   }

   public function publish( string $outputFile ): void {
      $script = new PhpScript();
      $scopeList = new ScopeList();
      $walk = new ModuleWalker( $this->module, $this->task, $script,
         $scopeList, $this->typeChecker );
      $walk->visitModule();
      $writer = new Writer();
      $writer->write( $script, $outputFile );
   }
}
