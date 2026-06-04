<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Tests\Unit\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Prevents production upgrade fatals when HolidayService gains new constructor dependencies.
 */
class HolidayServiceDiRegistrationTest extends TestCase
{
    public function testApplicationRegistersHolidaySuppressionMapperBeforeUserSettings(): void
    {
        $path = dirname(__DIR__, 3) . '/lib/AppInfo/Application.php';
        $source = file_get_contents($path);
        $this->assertIsString($source);

        if (!preg_match(
            '/registerService\(HolidayService::class,\s*function\s*\(\$c\)\s*\{\s*return new HolidayService\((.*?)\);\s*\}/s',
            $source,
            $matches,
        )) {
            $this->fail('HolidayService factory block not found in Application.php');
        }

        $factoryBody = $matches[1];
        $suppressPos = strpos($factoryBody, 'HolidaySuppressionMapper::class');
        $userSettingsPos = strpos($factoryBody, 'UserSettingsMapper::class');

        $this->assertNotFalse($suppressPos, 'HolidaySuppressionMapper must be wired into HolidayService');
        $this->assertNotFalse($userSettingsPos, 'UserSettingsMapper must be wired into HolidayService');
        $this->assertLessThan(
            $userSettingsPos,
            $suppressPos,
            'HolidaySuppressionMapper must be the second constructor argument (before UserSettingsMapper)',
        );
    }
}
