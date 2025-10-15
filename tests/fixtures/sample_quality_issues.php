<?php

/**
 * Test fixture: PHP file with code quality issues
 * Used for testing syntax and quality analysis functionality
 */

class QualityIssuesClass
{
    // Unused variable
    public function processData($input)
    {
        $timestamp = time(); // This variable is never used
        $processedData = strtoupper($input);
        
        return $processedData;
    }
    
    // Line too long
    public function aVeryLongMethodNameThatExceedsTypicalLineLengthLimitsAndShouldBeRefactoredToSomethingShorterAndMoreMeaningful($parameter1, $parameter2, $parameter3, $parameter4, $parameter5)
    {
        return $parameter1 + $parameter2 + $parameter3 + $parameter4 + $parameter5;
    }
    
    // Missing strict types declaration (file level issue)
    
    // Inconsistent indentation
    public function badIndentation()
    {
       $data = [];
          for ($i = 0; $i < 10; $i++) {
            $data[] = $i;
       }
        return $data;
    }
    
    // Dead code (unreachable)
    public function unreachableCode($condition)
    {
        return true;
        
        // This code is never reached
        $deadVariable = "never executed";
        return false;
    }
    
    // Complex cyclomatic complexity
    public function complexMethod($a, $b, $c, $d, $e)
    {
        if ($a > 0) {
            if ($b > 0) {
                if ($c > 0) {
                    if ($d > 0) {
                        if ($e > 0) {
                            return $a + $b + $c + $d + $e;
                        } else {
                            return $a + $b + $c + $d;
                        }
                    } else {
                        return $a + $b + $c;
                    }
                } else {
                    return $a + $b;
                }
            } else {
                return $a;
            }
        } else {
            return 0;
        }
    }
    
    // Missing return type hint
    public function missingReturnType($input)
    {
        return $input * 2;
    }
    
    // Missing parameter type hint
    public function missingParameterType($input): int
    {
        return (int) $input;
    }
    
    // Trailing whitespace issues (simulated with comments)
    public function trailingWhitespace()      // Trailing spaces here
    {
        $data = "test";       // More trailing spaces
        return $data;
    }
}