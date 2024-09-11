// WordPress dependencies.
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

// Internal dependencies.
import metadata from './block.json';

const Edit = ( props ) => {
	const { attributes, setAttributes } = props;
	return (
		<div { ...useBlockProps() }>
			<RichText
				allowedFormats={ [] }
				onChange={ ( loadingText ) => setAttributes( { loadingText } ) }
				placeholder={ __( 'Add loading text', 'dieline' ) }
				tagName="p"
				value={ attributes?.loadingText }
				withoutInteractiveFormatting
			/>
		</div>
	);
};

// Register the block type definition.
registerBlockType( metadata.name, {
	edit: Edit,
} );
