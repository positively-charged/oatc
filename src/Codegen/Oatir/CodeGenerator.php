<?php

declare( strict_types = 1 );

namespace Codegen\Oatir;

require_once CODEGEN_DIR . '/Oatir/ir.php';
require_once CODEGEN_DIR . '/Oatir/scope.php';
require_once CODEGEN_DIR . '/Oatir/Result.php';
require_once CODEGEN_DIR . '/Oatir/Runner.php';
require_once CODEGEN_DIR . '/Oatir/ModuleWalker.php';
require_once CODEGEN_DIR . '/Oatir/ExprWalker.php';

class CodeGenerator {
   private \Task $task;
   private \Module $module;
   private \Typing\TypeChecker $typeChecker;

   public function __construct( \Task $task,
      \Typing\TypeChecker $typeChecker ) {
      $this->task = $task;
      $this->module = $task->module;
      $this->typeChecker = $typeChecker;
   }

   public function generateIr( string $outputFile ): Archive {
      $archive = new Archive();
      $scopeList = new ScopeList();
      $walker = new ModuleWalker( $this->module, $this->task, $archive,
         $scopeList, $this->typeChecker );
      $walker->visitModule();
      return $archive;
      #$writer = new Writer();
      #$writer->write( $assembly, $outputFile );
   }
}
