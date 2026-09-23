import { useState } from "@wordpress/element";
import PortfolioList from "./PortfolioList";
import { __ } from "@wordpress/i18n";
import { decode } from "he"; // Import `he` to handle decoding HTML entities

const Portfolio = ({ categories, otherCategories, attributes }) => {
	const {
		showCategories,
		showOtherCategories,
		showSearch,
		headerColor,
		headerTextColor,
		categorySelectedLabels,
	} = attributes;

	const [selectedCategory, setSelectedCategory] = useState("all");
	const [otherSelectedCategory, setOtherSelectedCategory] = useState("all");
	const [searchTerm, setSearchTerm] = useState("");

	const handleCategoryClick = (e, categoryId) => {
		e.preventDefault();
		setSelectedCategory(categoryId);
	};

	const handleOtherCategoryClick = (e, categoryId) => {
		e.preventDefault();
		setOtherSelectedCategory(categoryId);
	};

	const handleSearchChange = (e) => {
		setSearchTerm(e.target.value.toLowerCase());
	};

	return (
		<>
			<div
				id="astra-portfolio"
				className="astra-portfolio-wrap astra-portfolio-style-1 astra-portfolio-show-on-scroll"
			>
				{(JSON.parse(attributes).showCategories ||
					JSON.parse(attributes).showOtherCategories ||
					JSON.parse(attributes).showSearch) && (
					<div
						className="astra-portfolio-filters"
						style={{
							backgroundColor: JSON.parse(attributes).headerColor,
						}}
					>
						<FilterCategories
							otherCategories={otherCategories}
							handleOtherCategoryClick={handleOtherCategoryClick}
							categories={categories}
							handleCategoryClick={handleCategoryClick}
							attributes={attributes}
						/>
						{JSON.parse(attributes).showSearch && (
							<SearchForm
								searchTerm={searchTerm}
								handleSearchChange={handleSearchChange}
							/>
						)}
					</div>
				)}

				<PortfolioList
					attributes={JSON.parse(attributes)}
					selectedCategory={selectedCategory}
					otherSelectedCategory={otherSelectedCategory}
					searchTerm={searchTerm}
				/>
			</div>
		</>
	);
};

// Component for category filters
const FilterCategories = ({
	otherCategories,
	handleOtherCategoryClick,
	categories,
	handleCategoryClick,
	attributes,
}) => (
	<div className="filters-wrap">
		{JSON.parse(attributes).showOtherCategories && (
			<OtherCategoryFilter
				categories={otherCategories}
				handleClick={handleOtherCategoryClick}
				attributes={attributes}
			/>
		)}
		{JSON.parse(attributes).showCategories && (
			<CategoryFilter
				categories={categories}
				handleClick={handleCategoryClick}
				attributes={attributes}
			/>
		)}
	</div>
);

// Component for category filter list with `categorySelectedLabels` filtering
const CategoryFilter = ({ categories, handleClick, attributes }) => {
	const [activeCategory, setActiveCategory] = useState(null);

	let selectedLabels;

	try {
		const parsedAttributes =
			typeof attributes === "string" ? JSON.parse(attributes) : attributes;
		selectedLabels = parsedAttributes.categorySelectedLabels;
	} catch (error) {
		console.error("Error parsing attributes:", error);
		selectedLabels = [];
	}

	// Parse showCategoriesAll separately
	let showCategoriesAllValue;
	try {
		const parsedShowCategoriesAll =
			typeof attributes === "string"
				? JSON.parse(attributes).showCategoriesAll
				: attributes.showCategoriesAll;
		showCategoriesAllValue = parsedShowCategoriesAll.showCategoriesAll;
	} catch (error) {
		showCategoriesAllValue = true; // default value
	}

	// Filter categories to display only selected labels if they exist
	const filteredCategories =
		selectedLabels && selectedLabels.length > 0
			? categories.filter((category) => selectedLabels.includes(decode(category.name)))
			: categories;

	const handleCategoryClick = (e, categoryId) => {
        e.preventDefault();
        setActiveCategory(categoryId);
        handleClick(e, categoryId);
    };

	return (
		<div className="astra-portfolio-categories-wrap">
			<ul className="astra-portfolio-categories filter-links">
				{showCategoriesAllValue && (
					<li>
						<a
							href="#"
							data-group="all"
							className={activeCategory === "all" ? "active" : ""}
                            onClick={(e) => handleCategoryClick(e, "all")}
                            style={{
                                color: activeCategory === "all" ? JSON.parse(attributes).activeHeaderTextColor : JSON.parse(attributes).headerTextColor,
                                textDecoration: "none",
                            }}
						>
							{__("All", "astra-portfolio")}
						</a>
					</li>
				)}
				{filteredCategories.map((category) => (
					(category.count !== 0 && <li key={category.id}>
						<a
							href="#"
							data-group={category.id}
							className={activeCategory === category.id ? `active ${category.name}` : `category ${category.name}`}
                            onClick={(e) => handleCategoryClick(e, category.id)}
							style={{
								color: activeCategory === category.id ? JSON.parse(attributes).activeHeaderTextColor : JSON.parse(attributes).headerTextColor,
								textDecoration: "none",
							}}
						>
							{decode(category.name)}
						</a>
					</li>)
				))}
			</ul>
		</div>
	);
};

// Component for category filter list
const OtherCategoryFilter = ({ categories, handleClick, attributes }) => {
	const [activeOtherCategory, setActiveOtherCategory] = useState(null);

	let selectedLabels;
	try {
		const parsedAttributes =
			typeof attributes === "string" ? JSON.parse(attributes) : attributes;
		selectedLabels = parsedAttributes.otherCategorySelectedLabels;
	} catch (error) {
		console.error("Error parsing attributes:", error);
		selectedLabels = [];
	}

	// Parse showOtherCategoriesAll separately
	let showOtherCategoriesAllValue;
	try {
		const parsedShowOtherCategoriesAll =
			typeof attributes === "string"
				? JSON.parse(attributes).showOtherCategoriesAll
				: attributes.showOtherCategoriesAll;
		showOtherCategoriesAllValue =
			parsedShowOtherCategoriesAll.showOtherCategoriesAll;
	} catch (error) {
		showOtherCategoriesAllValue = true; // default value
	}

	// Filter categories to display only selected labels if they exist
	const filteredOtherCategories =
    selectedLabels && selectedLabels.length > 0
        ? categories.filter((category) => selectedLabels.includes(decode(category.name)))
        : categories;


	const handleOtherCategoryClick = (e, categoryId) => {
        e.preventDefault();
        setActiveOtherCategory(categoryId);
        handleClick(e, categoryId);
    };

	return (
		<div className="astra-portfolio-categories-wrap">
			<ul className="astra-portfolio-categories filter-links">
				{showOtherCategoriesAllValue && (
					<li>
						<a
							href="#"
							data-group="all"
							className={activeOtherCategory === "all" ? "active" : ""}
                            onClick={(e) => handleOtherCategoryClick(e, "all")}
                            style={{
                                color: activeOtherCategory === "all" ? JSON.parse(attributes).activeHeaderTextColor : JSON.parse(attributes).headerTextColor,
                                textDecoration: "none",
                            }}
						>
							{__("All", "astra-portfolio")}
							
						</a>
					</li>
				)}
				{filteredOtherCategories.map((category) => (
					(category.count !== 0 && <li key={category.id}>
						<a
							href="#"
							data-group={category.id}
							className={activeOtherCategory === category.id ? `active ${category.name}` : `category ${category.name}`}
                            onClick={(e) => handleOtherCategoryClick(e, category.id)}
							style={{
								color: activeOtherCategory === category.id ? JSON.parse(attributes).activeHeaderTextColor : JSON.parse(attributes).headerTextColor,
								textDecoration: "none",
							}}
						>
							{decode(category.name)}
						</a>
					</li>)
				))}
			</ul>
		</div>
	);
};

// Component for search form
const SearchForm = ({ searchTerm, handleSearchChange }) => (
	<div className="search-form">
		<label className="screen-reader-text" htmlFor="astra-portfolio-search">
			{__("Search","astra-portfolio")}
		</label>
		<input
		    id="astra-portfolio-search" // Add unique id.
			name="astra-portfolio-search" // Add name attribute.
			placeholder="Search..."
			type="search"
			aria-describedby="live-search-desc"
			className="astra-portfolio-search"
			value={searchTerm}
			onChange={handleSearchChange} // Update search term when input changes.
		/>
	</div>
);

export default Portfolio;
