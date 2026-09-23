/**
 * WordPress dependencies.
 */
import domReady from "@wordpress/dom-ready";
import { createRoot } from "@wordpress/element";

import jsonValidator from "../../components/jsonValidator";

/**
 * Internal dependencies.
 */
import Portfolio from "./Portfolio";
import "./fstyle.css";

domReady(async function () {
	if (document.body.classList.contains("block-editor-page")) {
		return;
	}

	const getPortfolioBlocks = document.querySelectorAll(
		".portfolio-block-wrapper",
	);

	if (getPortfolioBlocks) {
		getPortfolioBlocks.forEach((item) => {
			const attributes = item.getAttribute("portfolio-block-attributes");
			const checkAndJsonParse = jsonValidator(attributes);

			let categories = [];
			let otherCategories = [];

			let root = document.createElement("div");
			document.body.appendChild(root);
			// root.classList.add("portfolio-block-test");

			const renderPortfolioBlock = (item, categories, otherCategories) => {
				try {
					if (checkAndJsonParse === false) {
						throw new Error('JSON parsing failed');
					} else {
						createRoot(item).render(
							<Portfolio
								categories={categories}
								otherCategories={otherCategories}
								attributes={attributes}
							/>,
						);
					}
				} catch (error) {
					console.error('Error with rendering portfolio block:', error);
				}
			};

			const fetchCategories = async () => {
				try {
					const catResponse = await fetch(
						"/wp-json/wp/v2/astra-portfolio-categories",
					);
					const catData = await catResponse.json();
					categories = catData;

					const otherCatResponse = await fetch(
						"/wp-json/wp/v2/astra-portfolio-other-categories",
					);
					const otherCatData = await otherCatResponse.json();
					otherCategories = otherCatData;

					renderPortfolioBlock(item, categories, otherCategories);
				} catch (error) {
					console.error("Error fetching portfolio categories:", error);
				}
			};

			fetchCategories();

			item.removeAttribute("portfolio-block-attributes");
		});
	}
});
