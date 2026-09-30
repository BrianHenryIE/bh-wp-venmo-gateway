/**
 * Run WP-CLI in the wp-env development site (the one the E2E tests run against).
 */
import { execFileSync } from 'child_process';

export function wpCli( args: string[] ): string {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
}

/**
 * Run every due (or past-due) event for a WP-Cron hook.
 *
 * WP-Cron may already have spawned and run the event on an earlier page load, in which case there is nothing
 * to run and WP-CLI exits non-zero; that is not a failure for callers who then assert the event's effect.
 */
export function runDueCronHook( hook: string ): string {
	try {
		return wpCli( [ 'cron', 'event', 'run', hook ] );
	} catch ( error ) {
		const output = String( ( error as { stderr?: string } ).stderr ?? '' ) + String( ( error as { stdout?: string } ).stdout ?? '' );
		if ( ! /Invalid cron event|no.*events/i.test( output ) ) {
			throw error;
		}
		return output;
	}
}
