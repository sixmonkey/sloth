<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\View;

use Sloth\View\Extensions\SlothViewExtension;

describe('SlothViewExtension', function (): void {

    describe('getHelpers()', function (): void {
        it('includes debug helper', function (): void {
            expect(new SlothViewExtension()->getHelpers())->toHaveKey('debug');
        });

        it('includes tel helper', function (): void {
            expect(new SlothViewExtension()->getHelpers())->toHaveKey('tel');
        });

        it('includes sanitize helper', function (): void {
            expect(new SlothViewExtension()->getHelpers())->toHaveKey('sanitize');
        });

        it('tel converts phone number to tel: URI', function (): void {
            $helpers = new SlothViewExtension()->getHelpers();
            $tel = $helpers['tel'];
            expect($tel('+49 123 456 789'))->toBe('tel:+49123456789');
        });
    });

    describe('getDirectives()', function (): void {
        it('includes WordPress hooks', function (): void {
            $directives = new SlothViewExtension()->getDirectives();
            expect($directives)->toHaveKey('wp_head');
            expect($directives)->toHaveKey('wp_footer');
            expect($directives)->toHaveKey('body_class');
            expect($directives)->toHaveKey('post_class');
        });

        it('includes url directive', function (): void {
            expect(new SlothViewExtension()->getDirectives())->toHaveKey('url');
        });

        it('includes module directive', function (): void {
            expect(new SlothViewExtension()->getDirectives())->toHaveKey('module');
        });

        it('includes i18n directives', function (): void {
            $directives = new SlothViewExtension()->getDirectives();
            expect($directives)->toHaveKey('__');
            expect($directives)->toHaveKey('_e');
            expect($directives)->toHaveKey('_n');
        });

        it('includes options directive', function (): void {
            expect(new SlothViewExtension()->getDirectives())->toHaveKey('options');
        });
    });

    describe('share()', function (): void {
        it('shares app instance', function (): void {
            expect(new SlothViewExtension()->share())->toHaveKey('app');
        });
    });
});
