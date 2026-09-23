import { InspectorControls, PanelColorSettings, useBlockProps } from "@wordpress/block-editor";
import {
	PanelBody,
	TextControl,
	SelectControl,
	TextareaControl,
	ToggleControl,
	FontSizePicker,
	RangeControl,
	FormTokenField,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { decode } from "he"; // Import `he` to handle decoding HTML entities

/**
 * Internal dependencies.
 */
import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import ItemsList from "./ItemsList";

const Edit = ({ attributes, setAttributes }) => {
	const [categories, setCategories] = useState([]);
	const [otherCategories, setOtherCategories] = useState([]);
	const [tags, setTags] = useState([]);
	const [overrideThemeStyle, setOverrideThemeStyle] = useState(false);

	useEffect(() => {
		apiFetch({ path: "/wp/v2/astra-portfolio-categories" })
			.then((data) => {
				setCategories(data);
			})
			.catch((error) => {
				console.error("Error fetching data:", error);
			});
	}, []);

	useEffect(() => {
		apiFetch({ path: "/wp/v2/astra-portfolio-other-categories" })
			.then((data) => {
				setOtherCategories(data);
			})
			.catch((error) => {
				console.error("Error fetching data:", error);
			});
	}, []);

	useEffect(() => {
		apiFetch({ path: "/wp/v2/astra-portfolio-tags" })
			.then((data) => {
				setTags(data);
			})
			.catch((error) => {
				console.error("Error fetching data:", error);
			});
	}, []);

	// Create options for FormTokenField
	const categoryOptions = categories.map((category) => ({
		label: category?.name,
		value: category?.id,
	}));

	const otherCategoryOptions = otherCategories.map((otherCategory) => ({
		label: otherCategory?.name,
		value: otherCategory?.id,
	}));

	const tagsOptions = tags.map((tag) => ({
		label: tag?.name,
		value: tag?.id,
	}));

	// Extract labels to display in the token field dropdown
	const categoryLabels = categoryOptions.map((category) => category?.label);

	// Extract labels to display in the token field dropdown
	const tagLabels = tagsOptions.map((tag) => tag?.label);

	// Handle selection change in FormTokenField
	const handleCategoryChange = (selectedCategories) => {
		// Find matching category IDs for selected decoded labels
		const selectedIds = selectedCategories
			.map((decodedLabel) => {
				// Match the decoded label back to the encoded label in `categoryOptions`
				const match = categoryOptions.find(
					(category) => decode(category.label) === decodedLabel
				);
				return match ? match.value : null; // Return ID if found
			})
			.filter((id) => id !== null); // Filter out non-matching labels
	
		const selectedLabels = selectedCategories
			.map((decodedLabel) => {
				// Match the decoded label back to the encoded label
				const match = categoryOptions.find(
					(category) => decode(category.label) === decodedLabel
				);
				return match ? match.label : null; // Return label if found
			})
			.filter((label) => label !== null); // Filter out non-matching labels
	
		// Update attributes with selected IDs and labels
		setAttributes({
			categorySelectedNum: selectedIds,       // Store selected IDs
			categorySelectedLabels: selectedLabels, // Store encoded labels
		});
	};

	// Handle selection change in FormTokenField
// Handle selection change in FormTokenField
const handleOtherCategoryChange = (selectedCategories) => {
    // Convert decoded labels back to their encoded counterparts and IDs
    const selectedIds = selectedCategories
        .map((decodedLabel) => {
            // Find the matching category by its decoded label
            const match = otherCategoryOptions.find(
                (category) => decode(category.label) === decodedLabel
            );
            return match ? match.value : null; // Return ID if found
        })
        .filter((id) => id !== null); // Remove null (non-matching) values

    const selectedLabels = selectedCategories
        .map((decodedLabel) => {
            // Find the original label (encoded)
            const match = otherCategoryOptions.find(
                (category) => decode(category.label) === decodedLabel
            );
            return match ? match.label : null;
        })
        .filter((label) => label !== null); // Remove null (non-matching) values

    // Update attributes with selected IDs and labels
    setAttributes({
        otherCategorySelectedNum: selectedIds, // Store selected IDs
        otherCategorySelectedLabels: selectedLabels, // Store selected labels
    });
};

	// Handle selection change in FormTokenField
	const handleTagsChange = (selectedTags) => {
		// Find matching category IDs for selected labels
		const selectedIds = selectedTags
			.map((label) => {
				const match = tagsOptions.find((tag) => tag.label === label);
				return match ? match?.value : null;
			})
			.filter((id) => id !== null); // Filter out any non-matching labels

		const selectedLabels = selectedTags
			.map((label) => {
				const match = tagsOptions.find((tag) => tag?.label === label);
				return match ? match?.label : null;
			})
			.filter((name) => name !== null);

		// Update attribute with array of selected category IDs
		setAttributes({
			tagsSelectedNum: selectedIds,
			tagsSelectedLabels: selectedLabels,
		});
	};

	const blockProps = useBlockProps();

	return (
		<div {...blockProps} >
			<InspectorControls>
			<PanelBody title={__("Header", "astra-portfolio")} initialOpen={false}>
				<h4 style={{marginTop: "0", fontSize: "13px"}} >{__("Category", "astra-portfolio")}</h4>
					<ToggleControl
						label={__("Show Categories Names", "astra-portfolio")}
						checked={attributes?.showCategories}
						onChange={(value) => {
							setAttributes({ showCategories: value });
						}}
					/>
					{attributes?.showCategories && (<ToggleControl
						label={__("Add 'All' to view all category portfolio", "astra-portfolio")}
						checked={attributes?.showCategoriesAll}
						onChange={(value) => {
							setAttributes({ showCategoriesAll: value });
						}}
					/>)}
					<hr style={{margin: "15px 0" }} />
					<h4 style={{marginTop: "0", fontSize: "13px"}} >{__("Other Category", "astra-portfolio")}</h4>
					<ToggleControl
						label={__("Show Other Categories Names", "astra-portfolio")}
						checked={attributes?.showOtherCategories}
						onChange={(value) => {
							setAttributes({ showOtherCategories: value });
						}}
					/>
					{ attributes?.showOtherCategories && (<ToggleControl
						label={__("Add 'All' to view all Other Category portfolio", "astra-portfolio")}
						checked={attributes?.showOtherCategoriesAll}
						onChange={(value) => {
							setAttributes({ showOtherCategoriesAll: value });
						}}
					/>)}
					<hr style={{ borderColor: "transparent",borderStyle: "solid" , margin: "10px 0", borderWidth: "0.01px" }} />


					<PanelColorSettings
					title={
					<span style={{ margin: "10px 0 0 0", display: "block", fontSize: "13px"}}>
						{__("Color")}
					</span>}
						colorSettings={[
							{
								value: attributes?.headerTextColor,
								onChange: (newColor) => {
									setAttributes({ headerTextColor: newColor });
								},
								label: __("Text", "astra-portfolio"),
								allowReset: true,
							},
							{
								value: attributes?.activeHeaderTextColor,
								onChange: (newColor) => {
									setAttributes({ activeHeaderTextColor: newColor });
								},
								label: __( "Active Text", "astra-portfolio"),
								allowReset: true,
							},
							{
								value: attributes?.headerColor,
								onChange: (newColor) => {
									setAttributes({ headerColor: newColor });
								},
								label: __("Background", "astra-portfolio"),
							},
						]}
					/>
					<hr style={{ 
						margin: "15px 0"}} />
					<h4 style={{marginTop: "0", fontSize: "13px"}} >{__("Search box", "astra-portfolio")}</h4>
					<ToggleControl
						label={__("Show Search Box", "astra-portfolio")}
						checked={attributes?.showSearch}
						onChange={(value) => {
							setAttributes({ showSearch: value });
						}}
					/>
				</PanelBody>
				<PanelBody title={__("Portfolio Settings", "astra-portfolio")} initialOpen={false}>
					<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Select Categories", "astra-portfolio")}</h4>
					<FormTokenField
						label={__("", "astra-portfolio")} //empty string to prevent dropdown issue
						value={attributes?.categorySelectedLabels.map((label) => decode(label))} // Decode for display
						suggestions={categoryOptions.map((category) => decode(category.label))}  // Decode suggestions
						onChange={handleCategoryChange} // Handle selection change
						__experimentalExpandOnFocus={true}
					/>
					<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Select Other Categories", "astra-portfolio")}</h4>
					<FormTokenField
						label={__("", "astra-portfolio")} //empty string to prevent dropdown issue
						value={attributes?.otherCategorySelectedLabels.map((label) => decode(label))} // Decode for display
						suggestions={otherCategoryOptions.map((otherCategory) => decode(otherCategory.label))} // Decode suggestions
						onChange={handleOtherCategoryChange} // Handle selection change
						__experimentalExpandOnFocus={true}
					/>
					<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Select Tags", "astra-portfolio")}</h4>
					<FormTokenField
						label={__("", "astra-portfolio")} //empty string to prevent dropdown issue
						value={attributes.tagsSelectedNum.map((id) => {
							const match = tagsOptions.find((tag) => tag?.value === id);
							return match ? match?.label : "";
						})}
						suggestions={tagLabels}
						onChange={handleTagsChange}
						__experimentalExpandOnFocus={true}
					/>
				</PanelBody>
				<PanelBody title={__("Layout Settings", "astra-portfolio")} initialOpen={false}>
				<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Layout", "astra-portfolio")}</h4>
				<SelectControl
						value={attributes?.thumbnailHoverStyle}
						options={[
							{ label: __("Masonry Layout", "astra-portfolio"), value: "default" },
							{ label: __("Grid Layout", "astra-portfolio"), value: "style-1" },
						]}
						onChange={(value) => setAttributes({ thumbnailHoverStyle: value })}
					/>
					
					<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Columns", "astra-portfolio")}</h4>
					<SelectControl
						help={<span style={{ margin: "20px 0" }}>{__("Choose up to 4 columns for your portfolio.", "astra-portfolio")}</span>} // Adjust the margin as needed

						value={attributes?.columns}
						options={[1, 2, 3, 4].map((item) => ({
							label: item,
							value: item,
						}))}
						onChange={(value) => setAttributes({ columns: value })}
					/>
					<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Portfolios per page", "astra-portfolio")}</h4>
					<TextControl
						help={__("Set the number of portfolios to load per page.", "astra-portfolio")}
						value={attributes?.itemsPerPage}
						onChange={(value) =>
							setAttributes({ itemsPerPage: parseInt(value) || 10 })
						}
						type="number"
						min="1"
						max="100"
					/>
					{attributes?.thumbnailHoverStyle === "style-1" && (
						<div>
							<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Scroll Speed", "astra-portfolio")}</h4>
							<SelectControl
								help={__("Set the image scroll speed (in seconds). Note: Scroll speed works only with Grid Layout.", "astra-portfolio")}
								value={attributes?.scrollSpeed}
								options={[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((item) => ({
									label: item,
									value: item,
								}))}
								onChange={(value) => setAttributes({ scrollSpeed: value })}
							/>
						</div>
					)}
					<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Quick View Text", "astra-portfolio")}</h4>
					<TextControl
						help={__("Add a Quick View Text here", "astra-portfolio")}
						value={attributes?.quickViewText}
						onChange={(value) => setAttributes({ quickViewText: value })}
					/>
					<h4 style={{fontSize: "11px", textTransform: "uppercase", marginBottom: "8px"}} >{__("Show Title At", "astra-portfolio")}</h4>
					<SelectControl
						help={__("Set portfolio title location.", "astra-portfolio")}
						value={attributes?.titlePosition}
						options={[
							{ label: __("Top", "astra-portfolio"), value: "top" },
							{ label: __("Bottom", "astra-portfolio"), value: "bottom" },
						]}
						onChange={(value) => setAttributes({ titlePosition: value })}
					/>
				</PanelBody>
				<PanelBody title={__("CTA Button", "astra-portfolio")} initialOpen={false}>
				<h4 style={{fontSize: "11px", marginBottom: "8px", textTransform: "uppercase"}} >{__("Button Text", "astra-portfolio")}</h4>
					<TextControl
						value={attributes?.loadMoreButtonText}
						onChange={(value) =>
							setAttributes({
								loadMoreButtonText: value || attributes.loadMoreButtonText,
							})
						}
					/>
					<hr style={{ borderColor: "transparent",borderStyle: "solid" , margin: "15px 0 0 0", borderWidth: "0.01px" }} />

							<div className="button-color-settings">
								<PanelColorSettings
								__experimentalIsRenderedInSidebar
									title={
										<span style={{ margin: "10px 0 0 0", display: "block", fontSize: "13px"}}>
											{__("Button Color")}
										</span>}
									colorSettings={[
										{
											value: attributes?.loadMoreBgColor,
											onChange: (newColor) =>
												setAttributes({ loadMoreBgColor: newColor }),
											label: __("Background Color", "astra-portfolio"),
											allowReset: true,
										},
										{
											value: attributes?.loadMoreTextColor,
											onChange: (newColor) =>
												setAttributes({ loadMoreTextColor: newColor }),
											label: __("Text Color", "astra-portfolio"),
											allowReset: true,
										},
									]}
								/>
					<hr style={{ borderColor: "transparent",borderStyle: "solid" , margin: "10px 0", borderWidth: "0.01px" }} />


								<PanelColorSettings
									title={
										<span style={{ margin: "10px 0 0 0", display: "block", fontSize: "13px"}}>
											{__("Button Hover Color")}
										</span>}
									colorSettings={[

										{
											value: attributes?.loadMoreHoverBgColor,
											onChange: (newColor) =>
												setAttributes({ loadMoreHoverBgColor: newColor }),
											label: __("Background Color", "astra-portfolio"),
										},
										{
											value: attributes?.loadMoreHoverTextColor,
											onChange: (newColor) =>
												setAttributes({ loadMoreHoverTextColor: newColor }),
											label: __("Text Color", "astra-portfolio"),
										},

									]}
								/>
							</div>
							<hr style={{ borderColor: "transparent",borderStyle: "solid" , margin: "10px 0", borderWidth: "0.01px" }} />

							<hr style={{ margin: "15px 0" }} />

							<div className="button-typography-settings">
								<h4 style={{marginTop: "0", fontSize: "13px"}} >{__("Typography", "astra-portfolio")}</h4>
								<FontSizePicker
									fontSizes={[
										{
											name: __("Small"),
											slug: "small",
											size: 14,
										},
										{
											name: __("Medium"),
											slug: "big",
											size: 16,
										},
										{
											name: __("Large"),
											slug: "large",
											size: 18,
										},
										{
											name: __("XL"),
											slug: "xl",
											size: 20,
										},
										{
											name: __("XXL"),
											slug: "xxl",
											size: 24,
										},
									]}
									value={attributes?.loadMoreSize}
									fallbackFontSize={14}
									disableCustomFontSizes={true}
									onChange={(value) => setAttributes({ loadMoreSize: value })}
								/>

								<hr style={{margin: "15px 0" }} />

								<h4 style={{marginTop: "0", fontSize: "13px"}} >{__("Dimensions", "astra-portfolio")}</h4>

								{/* Vertical Padding Slider */}
								<div className="padding-control">
									<h4 style={{margin: "0", fontSize: "11px", textTransform: "uppercase"}} >{__("Vertical Padding", "astra-portfolio")}</h4>
									<RangeControl
										value={attributes?.loadMoreVerticalPadding}
										onChange={(value) =>
											setAttributes({ loadMoreVerticalPadding: value })
										}
										min={0}
										max={20}
									/>
								</div>

								{/* Horizontal Padding Slider */}
								<div className="padding-control">
									<h4 style={{margin: "0", fontSize: "11px", textTransform: "uppercase"}} >{__("Horizontal Padding", "astra-portfolio")}</h4>
									<RangeControl
										value={attributes?.loadMoreHorizantalPadding}
										onChange={(value) =>
											setAttributes({ loadMoreHorizantalPadding: value })
										}
										min={0}
										max={40}
									/>
								</div>
								<hr style={{margin: "15px 0" }} />


								<h4 style={{marginTop: "0", fontSize: "13px"}} >{__("Border Settings", "astra-portfolio")}</h4>
								<h4 style={{margin: "0", fontSize: "11px",  textTransform: "uppercase"}} >{__("Border Width", "astra-portfolio")}</h4>
								<RangeControl
									value={attributes?.loadMoreBorderWidth}
									onChange={(value) =>
										setAttributes({ loadMoreBorderWidth: value })
									}
									min={0}
									max={20}
								/>

								<div className="border-radius-control">
									<h4 style={{margin: "0", fontSize: "11px", textTransform: "uppercase"}} >{__("Radius", "astra-portfolio")}</h4>
									<RangeControl
										value={attributes?.loadMoreBorderRadius}
										onChange={(value) =>
											setAttributes({ loadMoreBorderRadius: value })
										}
										min={0}
										max={20}
									/>
								</div>
								<hr style={{ borderColor: "transparent",borderStyle: "solid" , margin: "10px 0", borderWidth: "0.01px" }} />
								<PanelColorSettings
													title={
														<span style={{ margin: "10px 0 0 0", display: "block", fontSize: "13px"}}>
															{__("Border Color Settings")}
														</span>}
									colorSettings={[
										{
											value: attributes?.loadMoreBorderColor,
											onChange: (newColor) =>
												setAttributes({ loadMoreBorderColor: newColor }),
											label: __("Color", "astra-portfolio"),
										},
										{
											value: attributes?.loadMoreHoverBorderColor,
											onChange: (newColor) =>
												setAttributes({ loadMoreHoverBorderColor: newColor }),
											label: __("Hover Color", "astra-portfolio"),
										},
									]}
								/>

							</div>
				</PanelBody>
			</InspectorControls>

			<div
				id="astra-portfolio"
				className="astra-portfolio-wrap astra-portfolio-style-1 astra-portfolio-show-on-scroll"
				data-other-categories=""
				data-categories=""
				data-tags=""
			>
				{(attributes?.showCategories ||
					attributes?.showOtherCategories ||
					attributes?.showSearch) && (
					<div
						className="astra-portfolio-filters"
						style={{ backgroundColor: attributes?.headerColor }}
					>
						<div className="filters-wrap">
								{attributes?.showOtherCategories && (
							<div className="astra-portfolio-other-categories-wrap">
									<ul className="astra-portfolio-categories filter-links">
										{attributes?.showOtherCategoriesAll && (
											<li>
												<a
													href="#"
													data-group="all"
													style={{
														borderBottom: "none",
														color: attributes?.headerTextColor,
														textDecoration: "none",
													}}
												>
													{__("All", "astra-portfolio")}
												</a>
											</li>
										)}
										{attributes?.otherCategorySelectedLabels &&
										attributes?.otherCategorySelectedLabels.length > 0
											? attributes?.otherCategorySelectedLabels.map((label) => {
													// Find the category by matching the encoded label
													const category = otherCategories.find(
														(cat) => decode(cat?.name) === decode(label) // Decode both for safe comparison
													);
													return category ? (
														<li key={category?.id}>
															<a
																href="#"
																data-group={`${category?.id}`}
																className={`${decode(category?.name)}`} // Decode for use in the class
																style={{
																	borderBottom: "none",
																	color: attributes?.headerTextColor,
																	textDecoration: "none",
																}}
															>
																{decode(category?.name)} {/* Decode for proper display */}
															</a>
														</li>
													) : null;
											  })
											: otherCategories.map((category) => (
													(category.count !== 0 && <li key={category?.id}>
														<a
															href="#"
															data-group={`${category?.id}`}
															className={`${decode(category?.name)}`} // Decode for use in the class
															style={{
																borderBottom: "none",
																color: attributes?.headerTextColor,
																textDecoration: "none",
															}}
														>
															{decode(category?.name)} {/* Decode for proper display */}
														</a>
													</li>)
											  ))}
									</ul>
							</div>
								)}

								{attributes?.showCategories && (
							<div className="astra-portfolio-categories-wrap">
									<ul className="astra-portfolio-categories filter-links">
										{attributes?.showCategoriesAll && (
											<li>
												<a
													href="#"
													data-group="all"
													style={{
														borderBottom: "none",
														color: attributes?.headerTextColor,
														textDecoration: "none",
													}}
												>
													{__("All", "astra-portfolio")}
												</a>
											</li>
										)}
										{attributes?.categorySelectedLabels &&
										attributes?.categorySelectedLabels?.length > 0
											? attributes?.categorySelectedLabels.map((label) => {
													const category = categories.find(
														(cat) => decode(cat?.name) === decode(label) // Decode both for safe comparison
													);
													return category ? (
														<li key={category?.id}>
															<a
																href="#"
																data-group={`${category?.id}`}
																className={`${category?.name}`}
																style={{
																	borderBottom: "none",
																	color: attributes?.headerTextColor,
																	textDecoration: "none",
																}}
															>
																{decode(category?.name)} {/* Decode for proper display */}
															</a>
														</li>
													) : null;
											  })
											: categories.map((category) => (
													(category.count !== 0 && <li key={category?.id}>
														<a
															href="#"
															data-group={`${category?.id}`}
															className={`${decode(category?.name)}`} // Decode for use in the class
															style={{
																borderBottom: "none",
																color: attributes?.headerTextColor,
																textDecoration: "none",
															}}
														>
                											{decode(category?.name)} {/* Decode for proper display */}
														</a>
													</li>)
											  ))}
									</ul>
							</div>
								)}
						</div>

						{attributes?.showSearch && (
							<div className="search-form">
								<label
									className="screen-reader-text"
									htmlFor="astra-portfolio-search"
								>
									{__("Search", "astra-portfolio")}
								</label>
								<input
									id="astra-portfolio-search" // Add unique id.
									name="astra-portfolio-search" // Add name attribute.
									placeholder="Search..."
									type="search"
									aria-describedby="live-search-desc"
									className="astra-portfolio-search"
								/>
							</div>
						)}
					</div>
				)}

				<ItemsList attributes={attributes} />
			</div>
		</div>
	);
};

export default Edit;
