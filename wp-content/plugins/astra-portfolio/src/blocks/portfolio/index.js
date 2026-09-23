/**
 * BLOCK: wp-portfolio-block
 */

/**
 * WordPress dependencies.
 */
import { __ } from "@wordpress/i18n";
import { registerBlockType } from "@wordpress/blocks";
/**
 * Internal dependencies.
 */
import "./style.css";
import Edit from "./Edit";

let imgUrl = astraPortfolioData.imageUrl;
imgUrl += '/assets/images/preview.svg';

registerBlockType("astra-portfolio/wp-portfolio", {
	apiVersion: 3,
	title: "WP Portfolio",
	icon: (
		<svg
			width="150"
			height="129"
			viewBox="0 0 150 129"
			fill="currentColor"
			xmlns="http://www.w3.org/2000/svg"
		>
			<path
				d="M93.7443 88.3634C93.7443 89.8069 93.2155 91.0687 92.1537 92.1385C91.0899 93.2 89.8339 93.7266 88.3859 93.7266H61.6018C60.1454 93.7266 58.8895 93.2 57.8339 92.1385C56.7638 91.0667 56.2433 89.8172 56.2433 88.3634V74.9792H0V115.148C0 118.833 1.30966 121.978 3.93106 124.603C6.55039 127.221 9.70474 128.53 13.392 128.53H136.6C140.279 128.53 143.437 127.219 146.057 124.603C148.678 121.978 149.994 118.833 149.994 115.148V74.9792H93.7443V88.3634Z"
				fill="#currentColor"
			/>
			<path
				d="M85.7107 74.9792H64.2851V85.6849H85.7107V74.9792Z"
				fill="#currentColor"
			/>
			<path
				d="M146.063 25.3579C143.443 22.7372 140.285 21.4341 136.606 21.4341H107.141V8.03961C107.141 5.80512 106.362 3.90725 104.8 2.346C103.238 0.78682 101.344 0 99.109 0H50.891C48.66 0 46.7658 0.78682 45.1938 2.346C43.6321 3.90106 42.8492 5.80512 42.8492 8.03961V21.4341H13.392C9.70474 21.4341 6.54832 22.7393 3.93106 25.3579C1.30966 27.9786 0 31.13 0 34.8142V66.9478H150V34.8142C150 31.13 148.684 27.9786 146.063 25.3579ZM96.4256 21.4341H53.5723V10.714H96.4256V21.4341Z"
				fill="#currentColor"
			/>
		</svg>
	),
	category: "widgets",
	attributes: {
		showPortfolioOn: { type: "string", default: stored["show-portfolio-on"] },
		previewBar: { type: "string", default: stored["preview-bar-loc"] },
		columns: { type: "string", default: stored["no-of-columns"] },
		titlePosition: { type: "string", default: stored["portfolio-title-loc"] },
		callToAction: { type: "string", default: stored["no-more-sites-message"] },
		itemsPerPage: { type: "number", default: stored["per-page"] },
		scrollSpeed: { type: "string", default: stored["scroll-speed"] },
		enableMasonry: { type: "boolean", default: false },
		thumbnailHoverStyle: { type: "string", default: stored["grid-style"] },
		// useThemeBtn: {
		// 	type: "boolean",
		// },
		loadMoreButtonText: {
			type: "string",
			default: "Load More",
		},
		loadMoreBgColor: {
			type: "string",
			default: "#046bd2",
		},
		loadMoreHoverBgColor: {
			type: "string",
			default: "#000"
		},
		loadMoreTextColor: {
			type: "string",
			default: "#fff"
		},
		loadMoreHoverTextColor: {
			type: "string",
			default: "#fff",
		},
		loadMoreSize: {
			type: "number",
			default: "16",
		},
		loadMoreVerticalPadding: {
			type: "number",
			default: "12",
		},
		loadMoreHorizantalPadding: {
			type: "number",
			default: "22",
		},
		loadMoreBorderRadius: {
			type: "number",
			default: "4"
		},
		loadMoreBorderWidth: {
			type: "number",
			default: "0"
		},
		loadMoreBorderColor: {
			type: "string",
		},
		loadMoreHoverBorderColor: {
			type: "string",
		},
		categorySelectedNum: {
			type: "array",
			default: [],
		},
		otherCategorySelectedNum: {
			type: "array",
			default: [],
		},
		categorySelectedLabels: {
			type: "array",
			default: [],
		},
		otherCategorySelectedLabels: {
			type: "array",
			default: [],
		},
		tagsSelectedNum: {
			type: "array",
			default: [],
		},
		tagsSelectedLabels: {
			type: "array",
			default: [],
		},
		quickViewText: {
			type: "string",
			default: "Quick View",
		},
		showCategories: {
			type: "boolean",
			default: true,
		},
		showOtherCategories: {
			type: "boolean",
			default: false,
		},
		showSearch: {
			type: "boolean",
			default: true,
		},
		showCategoriesAll: {
			type: "boolean",
			default: true,
		},
		showOtherCategoriesAll: {
			type: "boolean",
			default: true,
		},
		headerColor: {
			type: "string",
		},
		headerTextColor: {
			type: "string",
		},
		activeHeaderTextColor: {
			type: "string",
		},
		isPreview: {
			type: "boolean",
			default: false,
		},
	},
	example: {
		attributes: {
			isPreview: true,
		},
	},
	description: __("Display stunning portfolios with grid or masonry layouts!", "astra-portfolio"),
	edit: ( props ) =>
		props.attributes.isPreview ? <img width="100%" src={imgUrl} alt="" /> : <Edit { ...props } />,
	save() {
		return null;
	},

});
