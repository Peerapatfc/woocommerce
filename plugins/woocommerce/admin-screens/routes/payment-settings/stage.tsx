/**
 * External dependencies
 */
import { Page } from '@wordpress/admin-ui';
import { Button, Notice, Spinner } from '@wordpress/components';
import { DataForm, useFormValidity } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { useParams } from '@wordpress/route';

/**
 * Internal dependencies
 */
import { useSettingsEntity } from './save';
import { AREA, getScreen, useScreenDefinition } from './screen';
import type { SettingsScreen, ScreenDefinition } from './screen';
import './style.scss';

function LoadError( { error }: { error: Error } ) {
	return (
		<Notice status="error" isDismissible={ false }>
			{ error.message }
		</Notice>
	);
}

function SettingsForm( {
	screen,
	definition,
}: {
	screen: SettingsScreen;
	definition: ScreenDefinition;
} ) {
	const {
		data,
		isDirty,
		isSaving,
		loadError,
		notice,
		clearNotice,
		edit,
		save,
		discard,
	} = useSettingsEntity( screen );
	const { validity, isValid } = useFormValidity(
		data ?? {},
		definition.fields,
		definition.form
	);

	if ( loadError ) {
		return <LoadError error={ loadError } />;
	}
	if ( ! data ) {
		return <Spinner />;
	}

	// A field whose module failed to load renders with default controls, which may not
	// represent its value correctly, so saving waits until the screen loads completely.
	const canSave = isDirty && isValid && definition.errors.length === 0;
	return (
		<Page
			title={ screen.title }
			showSidebarToggle={ false }
			hasPadding
			actions={
				<>
					<Button
						variant="secondary"
						size="compact"
						disabled={ isSaving || ! isDirty }
						onClick={ discard }
					>
						{ __( 'Discard changes', 'woocommerce' ) }
					</Button>
					<Button
						variant="primary"
						size="compact"
						isBusy={ isSaving }
						disabled={ isSaving || ! canSave }
						onClick={ () => void save() }
					>
						{ __( 'Save changes', 'woocommerce' ) }
					</Button>
				</>
			}
		>
			<div className="wc-settings-screen__content">
				{ definition.errors.map( ( error ) => (
					<Notice
						key={ error.message }
						status="warning"
						isDismissible={ false }
					>
						{ error.message }
					</Notice>
				) ) }
				{ notice && (
					<Notice status={ notice.status } onRemove={ clearNotice }>
						{ notice.message }
					</Notice>
				) }
				<DataForm
					data={ data }
					fields={ definition.fields }
					form={ definition.form }
					validity={ validity }
					onChange={ edit }
				/>
			</div>
		</Page>
	);
}

function ScreenContent( { screen }: { screen: SettingsScreen } ) {
	const { definition, error } = useScreenDefinition( screen );

	if ( error ) {
		return <LoadError error={ error } />;
	}
	if ( ! definition ) {
		return <Spinner />;
	}

	return <SettingsForm screen={ screen } definition={ definition } />;
}

function SettingsStage() {
	// useParams() is untyped without a registered router.
	const { page }: { page?: string } = useParams( { strict: false } );
	const screen = getScreen( AREA, page );

	return screen ? <ScreenContent screen={ screen } /> : null;
}

export const stage = SettingsStage;
