<?php

declare( strict_types = 1 );

require_once CODEGEN_DIR . '/X64/scope.php';
require_once CODEGEN_DIR . '/X64/Content.php';
require_once CODEGEN_DIR . '/X64/Instruction.php';
require_once CODEGEN_DIR . '/X64/InstructionArg.php';
require_once CODEGEN_DIR . '/X64/InstructionIterator.php';
require_once CODEGEN_DIR . '/X64/Sequence.php';
require_once CODEGEN_DIR . '/X64/Writer.php';
require_once CODEGEN_DIR . '/X64/ModuleWalker.php';
require_once CODEGEN_DIR . '/X64/ExprWalker.php';
require_once CODEGEN_DIR . '/X64/CallWalker.php';
require_once CODEGEN_DIR . '/X64/Machine.php';
require_once CODEGEN_DIR . '/X64/Result.php';
require_once CODEGEN_DIR . '/X64/ir.php';
require_once CODEGEN_DIR . '/X64/CodeGenerator.php';
require_once CODEGEN_DIR . '/X64/RegisterAllocator.php';
require_once CODEGEN_DIR . '/X64/RegisterFile.php';
require_once CODEGEN_DIR . '/X64/StackFrame.php';
require_once CODEGEN_DIR . '/X64/BlockDumper.php';
