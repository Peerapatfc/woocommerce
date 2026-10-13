/**
 * External dependencies
 */
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useState } from 'react';

/**
 * Internal dependencies
 */
import { NO_KEY, toError } from './screen';
import type { SettingsScreen, SettingsRecord } from './screen';

export interface SaveNotice {
	status: 'success' | 'error';
	message: string;
}

// Every store has this metadata selector, but core-data's types leave it out.
interface ResolutionSelectors {
	getResolutionError: ( selector: string, args: unknown[] ) => unknown;
}

/**
 * Edit, save and discard the settings entity of a screen.
 */
export function useSettingsEntity( screen: SettingsScreen ) {
	const { kind, name } = screen.entity;
	const [ notice, setNotice ] = useState< SaveNotice >();
	const { record, data, isDirty, isSaving, resolutionError } = useSelect(
		( select ) => {
			const core = select( coreStore );
			return {
				record: core.getEntityRecord( kind, name, NO_KEY ),
				data: core.getEditedEntityRecord( kind, name, NO_KEY ) as
					SettingsRecord | undefined,
				isDirty: core.hasEditsForEntityRecord( kind, name, NO_KEY ),
				isSaving: core.isSavingEntityRecord( kind, name, NO_KEY ),
				resolutionError: (
					core as unknown as ResolutionSelectors
				 ).getResolutionError( 'getEntityRecord', [
					kind,
					name,
					NO_KEY,
				] ),
			};
		},
		[ kind, name ]
	);
	const { editEntityRecord, saveEditedEntityRecord } =
		useDispatch( coreStore );

	const edit = ( edits: SettingsRecord ) => {
		if ( isSaving ) {
			return;
		}
		setNotice( undefined );
		void editEntityRecord( kind, name, NO_KEY, edits );
	};

	const save = async () => {
		if ( isSaving || ! isDirty ) {
			return;
		}
		setNotice( undefined );
		try {
			// core-data stores the record the server returns, so the page shows what was saved.
			await saveEditedEntityRecord( kind, name, NO_KEY, {
				throwOnError: true,
			} );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'woocommerce' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: toError(
					error,
					__( 'Unable to save settings.', 'woocommerce' )
				).message,
			} );
		}
	};

	const discard = () => {
		setNotice( undefined );
		if ( record ) {
			void editEntityRecord( kind, name, NO_KEY, record );
		}
	};

	return {
		data,
		isDirty,
		isSaving,
		loadError: resolutionError ? toError( resolutionError ) : undefined,
		notice,
		clearNotice: () => {
			setNotice( undefined );
		},
		edit,
		save,
		discard,
	};
}
