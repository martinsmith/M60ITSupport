import { useState, useEffect } from "@wordpress/element";
import WebLightbox from "./WebLightbox";
import VideoLightbox from "./VideoLightbox";
import Masonry, { ResponsiveMasonry } from "react-responsive-masonry"; // Import masonry components
import he from "he";
import { __ } from "@wordpress/i18n";


const PortfolioList = ({
	attributes,
	selectedCategory,
	searchTerm,
	otherSelectedCategory,
}) => {
	const [list, setList] = useState([]);
	const [visibleItems, setVisibleItems] = useState(attributes.itemsPerPage);
	const [isHovered, setIsHovered] = useState(false);
	const [selectedIframe, setSelectedIframe] = useState(null);
	const [selectedTitle, setSelectedTitle] = useState("");
	const [videoUrl, setVideoUrl] = useState("");
	const [page, setPage] = useState(1); // Current page state
	const [hasMore, setHasMore] = useState(true); // Check if more items are available
	const [totalItems, setTotalItems] = useState(null); // Total items available
	const [isLoading, setIsLoading] = useState(false);

	const fetchData = async (page) => {
		setIsLoading(true);
		try {
			// Construct the API URL based on whether a category is selected
			const baseUrl = astraPortfolioData.apiUrl;

			// Add selectedCategory to the URL if it's not "all"
			const selectedCategoryParam = selectedCategory !== "all"
			? `&astra-portfolio-categories=${selectedCategory}`
			: "";

			// Add selectedCategory to the URL if it's not "all"
			const otherSelectedCategoryParam = otherSelectedCategory !== "all"
			? `&astra-portfolio-other-categories=${otherSelectedCategory}`
			: "";

			// Join category IDs with a comma if there are multiple selections
			const categoryParam =
				attributes?.categorySelectedNum &&
				attributes?.categorySelectedNum.length > 0
					? `&astra-portfolio-categories=${attributes?.categorySelectedNum.join(
							",",
					  )}`
					: "";

			const otherCategoryParam =
				attributes?.otherCategorySelectedNum &&
				attributes?.otherCategorySelectedNum.length > 0
					? `&astra-portfolio-other-categories=${attributes.otherCategorySelectedNum.join(
							",",
					  )}`
					: "";
			const tagsParam =
				attributes?.tagsSelectedNum && attributes?.tagsSelectedNum.length > 0
					? `&astra-portfolio-tags=${attributes?.tagsSelectedNum.join(",")}`
					: "";
			const url = `${baseUrl}?per_page=${visibleItems}${selectedCategoryParam}${otherSelectedCategoryParam}&page=${page}${categoryParam}${otherCategoryParam}${tagsParam}`;
			const response = await fetch(url);
			const data = await response.json();

			// Get total number of items from header if not already set
			if (totalItems === null) {
				const total = response.headers.get("X-WP-Total");
				setTotalItems(parseInt(total, 10));
			}

			// Append new items to the list or replace list if it's the first page
			setList((prevList) => (page === 1 ? data : [...prevList, ...data]));

			setIsLoading(false);
		} catch (error) {
			console.error("Error fetching data:", error);
		}
	};

	// useEffect to handle fetching items initially and when the category changes
	useEffect(() => {
		// Reset list and fetch data for the first page when category changes
		setList([]); // Clear current list
		setTotalItems(null); // Reset total items count
		setPage(1); // Reset to the first page
		fetchData(1);
	}, [selectedCategory,otherSelectedCategory, attributes.categorySelectedNum, attributes.otherCategorySelectedNum]);

	// useEffect to handle pagination when page changes
	useEffect(() => {
		// Only fetch data if the page number changes (not on category change)
		if (page > 1) fetchData(page);
	}, [page]);

	// Load more items when the button is clicked
	const loadMoreItems = () => {
		if (hasMore) {
			setPage((prevPage) => prevPage + 1); // Increment page to load the next set of items
		}
	};

	const updateSelectedIframe = (url, portfolioName, type = null) => {
		if (type === "video") {
			setSelectedIframe({ type: "video", url });
		} else {
			setSelectedIframe(url);
			setSelectedTitle(portfolioName);
		}
	};

	const filteredList = list.filter((item) => item?.title?.rendered.toLowerCase().includes(searchTerm));

	const columnNum = Number(attributes?.columns);

	return (
		<div
			className="astra-portfolio-shortcode-wrap astra-portfolio-grid astra-portfolio astra-portfolio-row"
			style={{ position: "relative" }}
		>
			{isLoading ? (
						<div className="spinner-div">
							<span className="spinner-block"></span>
						</div>
					) :  (filteredList?.length > 0 ? (
				<>
					{attributes?.thumbnailHoverStyle === "style-1" ? (
						<div className="portfolio-grid">
							{filteredList.map((item) => (
								<PortfolioItem
									key={item?.id}
									item={item}
									attributes={attributes}
									updateSelectedIframe={updateSelectedIframe}
								/>
							))}
						</div>
					) : (
						<div className="portfolio-grid">
							<ResponsiveMasonry
								columnsCountBreakPoints={{ 350: 1, 500: 2, 900: columnNum }}
								gutterBreakPoints={{ 0: "1px" }}
							>
								<Masonry gutter="1px">
									{filteredList.map((item) => (
										<div key={item?.id}>
											<PortfolioItem
												item={item}
												attributes={attributes}
												updateSelectedIframe={updateSelectedIframe}
											/>
										</div>
									))}
								</Masonry>
							</ResponsiveMasonry>
						</div>
					)}

					{list?.length < totalItems && !isLoading && (
						<LoadMoreButton
							isHovered={isHovered}
							setIsHovered={setIsHovered}
							loadMoreItems={loadMoreItems} // Pass the loadMoreItems function
							attributes={attributes}
							isLoading={isLoading}
						/>
					)}
				</>
			) : (
						__("No items found", "astra-portfolio")
			))}

			{selectedIframe &&
				typeof selectedIframe === "object" &&
				selectedIframe?.type === "video" && (
					<VideoLightbox
						videoUrl={selectedIframe?.url}
						onClose={() => setSelectedIframe(null)}
					/>
				)}

			{selectedIframe && typeof selectedIframe !== "object" && (
				<WebLightbox
					key={selectedIframe}
					url={selectedIframe}
					title={selectedTitle}
					onClose={() => setSelectedIframe(null)}
				/>
			)}
		</div>
	);
};

// Component for individual portfolio items
const PortfolioItem = ({ item, attributes, updateSelectedIframe }) => {
	let classes_new_tab = "";
	if (1 == item["astra-site-open-in-new-tab"]) {
		classes_new_tab = "open-in-new-tab";
	}


	const itemClasses = `site-single ${classes_new_tab} ${
		item["portfolio-type"] || ""
	} ${item["astra-site-open-portfolio-in"] || ""} ${
		attributes?.thumbnailHoverStyle === "style-1"
			? columnClass[attributes?.columns]
			: ""
	}`;
	const rootstyle = attributes?.thumbnailHoverStyle;
	const style =
		rootstyle === "style-1"
			? {
					backgroundImage: `url(${item["thumbnail-image-url"]})`,
					paddingTop: "100%",
					transition: `all ease-in-out ${attributes?.scrollSpeed || 0}s`,
			  }
			: {};

	return (
		<div className={`${itemClasses} portfolio-item`}>
			<div className="inner">
				{attributes?.titlePosition === "top" && <ItemTitle item={item} />}
				<List
					attributes={attributes}
					item={item}
					style={style}
					rootstyle={rootstyle}
					updateSelectedIframe={updateSelectedIframe}
				/>
				{attributes?.titlePosition === "bottom" && <ItemTitle item={item} />}
			</div>
		</div>
	);
};

// Component for item title
const ItemTitle = ({ item }) => (
	<div className="template-meta">
		<div className="item-title">
			{he.decode(item?.title?.rendered)}
			{item["astra-site-type"] && (
				<span className={`site-type ${item["astra-site-type"]}`}>
					{item["astra-site-type"]}
				</span>
			)}
		</div>
	</div>
);

// Component for list (portfolio types)
const List = ({ attributes, item, style, rootstyle, updateSelectedIframe }) => {
	switch (item["portfolio-type"]) {
		case "page":
			return (
				<SinglePageContent
					item={item}
					style={style}
					attributes={attributes}
					rootstyle={rootstyle}
					updateSelectedIframe={updateSelectedIframe}
				/>
			);
		case "video":
			return (
				<VideoContent
					item={item}
					style={style}
					attributes={attributes}
					rootstyle={rootstyle}
					updateSelectedIframe={updateSelectedIframe}
				/>
			);
		case "image":
			return (
				<ImageContent
					item={item}
					style={style}
					attributes={attributes}
					rootstyle={rootstyle}
					updateSelectedIframe={updateSelectedIframe}
				/>
			);
		case "iframe":
			return (
				<IframeContent
					item={item}
					style={style}
					attributes={attributes}
					rootstyle={rootstyle}
					updateSelectedIframe={updateSelectedIframe}
				/>
			);
		default:
			return null;
	}
};

// Component for video content
const VideoContent = ({
	item,
	style,
	attributes,
	rootstyle,
	updateSelectedIframe,
}) => (
	<span
		style={style}
		className="site-preview"
		title={item?.title?.rendered}
		data-elementor-open-lightbox="yes"
		onClick={() =>
			updateSelectedIframe(
				item["portfolio-video-url"],
				item["slug"],
				"video",
			)
		}
	>
		{"style-1" !== rootstyle && item["thumbnail-image-url"] && (
			<img
				className="lazy"
				src={item["thumbnail-image-url"]}
				alt={item["thumbnail-image-meta"]["alt"]}
			/>
		)}
		{"yes" === attributes["show-quick-view"] && (
			<span className="view-demo-wrap">
				<span
					className="view-demo"
					onClick={() =>
						updateSelectedIframe(
							item["portfolio-video-url"],
							item["slug"],
							"video",
						)
					}
				>
					{attributes?.quickViewText || __("Quick View", "astra-portfolio")}
				</span>
			</span>
		)}
	</span>
);

// Component for image content
const ImageContent = ({
	item,
	style,
	attributes,
	rootstyle,
	updateSelectedIframe,
}) => (
	<span
		style={style}
		className="site-preview"
		title={item?.title?.rendered}
		data-elementor-open-lightbox="yes"
		onClick={() =>
			updateSelectedIframe(item["lightbox-image-url"], item["title"]["rendered"])
		}
	>
		{"style-1" !== rootstyle && item["thumbnail-image-url"] && (
			<>
				<img
					className="lazy"
					src={item["thumbnail-image-url"]}
					alt={item["thumbnail-image-meta"]["alt"]}
				/>
				<noscript>
					<img
						src={item["thumbnail-image-url"]}
						alt={item["thumbnail-image-meta"]["alt"]}
					/>
				</noscript>
			</>
		)}
		{"yes" === attributes["show-quick-view"] && (
			<span className="view-demo-wrap">
				<span
					className="view-demo"
					onClick={() =>
						updateSelectedIframe(item["lightbox-image-url"], item["title"]["rendered"])
					}
				>
					{attributes?.quickViewText ||  __("Quick View", "astra-portfolio")}
				</span>
			</span>
		)}
	</span>
);

// Component for iframe content
const IframeContent = ({
	item,
	style,
	attributes,
	rootstyle,
	updateSelectedIframe,
}) => {
	let classes_new_tab = "";
	if (1 == item["astra-site-open-in-new-tab"]) {
		classes_new_tab = "open-in-new-tab";
	}

	return (
		<>
			{classes_new_tab === "" ? (
				<span
					className="site-preview"
					style={style}
					aria-label={`View ${item?.title?.rendered} website`}
					onClick={() =>
						updateSelectedIframe(item["astra-site-url"], item["title"]["rendered"])
					}
				>
					{"style-1" !== rootstyle && item["thumbnail-image-url"] && (
						<img
							className="lazy"
							src={item["thumbnail-image-url"]}
							alt={item["thumbnail-image-meta"].alt}
						/>
					)}
					{"yes" === attributes["show-quick-view"] && (
						<span className="view-demo-wrap">
							<span
								className="view-demo"
								onClick={() =>
									updateSelectedIframe(item["astra-site-url"], item["title"]["rendered"])
								}
							>
								{attributes?.quickViewText || __("Quick View", "astra-portfolio")}
							</span>
						</span>
					)}
				</span>
			) : (
				<a
					className="site-preview"
					href={item["astra-site-url"]}
					target="_blank"
					style={style}
					aria-label={`View ${item?.title?.rendered} website`}
				>
					{"style-1" !== rootstyle && item["thumbnail-image-url"] && (
						<img
							className="lazy"
							src={item["thumbnail-image-url"]}
							alt={item["thumbnail-image-meta"].alt}
						/>
					)}
					{"yes" === attributes["show-quick-view"] && (
						<span className="view-demo-wrap">
							<span className="view-demo">
								{attributes?.quickViewText ||  __("Quick View", "astra-portfolio")}
							</span>
						</span>
					)}
				</a>
			)}
		</>
	);
};

// Component for Single Page content
const SinglePageContent = ({
	item,
	style,
	attributes,
	rootstyle,
	updateSelectedIframe,
}) => {
	return (
		<>
			{item["astra-site-open-portfolio-in"] === "new-tab" ||
			item["astra-site-open-portfolio-in"] === "same-tab" ? (
				<a
					className="site-preview"
					href={item["link"]}
					target={
						item["astra-site-open-portfolio-in"] === "same-tab"
							? "_self"
							: "_blank"
					}
					style={style}
					aria-label={`View ${item?.title?.rendered} page`}
				>
					{"style-1" !== rootstyle && item["thumbnail-image-url"] && (
						<img
							className="lazy"
							src={item["thumbnail-image-url"]}
							alt={item["thumbnail-image-meta"].alt}
						/>
					)}
					{"yes" === attributes["show-quick-view"] && (
						<span className="view-demo-wrap">
							<span className="view-demo">
								{attributes?.quickViewText || __("Quick View", "astra-portfolio")}
							</span>
						</span>
					)}
				</a>
			) : (
				<span
					className="site-preview"
					style={style}
					aria-label={`View ${item?.title?.rendered} page`}
					onClick={() => updateSelectedIframe(item["link"], item["title"]["rendered"])}
				>
					{"style-1" !== rootstyle && item["thumbnail-image-url"] && (
						<img
							className="lazy"
							src={item["thumbnail-image-url"]}
							alt={item["thumbnail-image-meta"].alt}
						/>
					)}
					{"yes" === attributes["show-quick-view"] && (
						<span className="view-demo-wrap">
							<span
								className="view-demo"
								onClick={() => updateSelectedIframe(item["link"], item["title"]["rendered"])}
							>
								{attributes?.quickViewText || __("Quick View", "astra-portfolio")}
							</span>
						</span>
					)}
				</span>
			)}
		</>
	);
};

// Component for Load More button
const LoadMoreButton = ({
	isHovered,
	setIsHovered,
	loadMoreItems,
	attributes,
	isLoading,
}) => {
	const calculateBtnFontSize = () => {
		return `${attributes?.loadMoreSize}px`;
	};

	return (
		<div
			style={{
				width: "100%",
				display: "flex",
				justifyContent: "center",
				alignItems: "center",
			}}
		>
			{isLoading ? (
				<div className="spinner-div">
					<span className="spinner-block"></span>
				</div>
			) : (
				<button
					onClick={loadMoreItems}
					onMouseEnter={() => setIsHovered(true)}
					onMouseLeave={() => setIsHovered(false)}
					style={{
						backgroundColor: isHovered
								? attributes?.loadMoreHoverBgColor
								: attributes?.loadMoreBgColor,
						color: isHovered
								? attributes?.loadMoreHoverTextColor
								: attributes?.loadMoreTextColor,
						transition: "background-color 0.3s, color 0.3s",
						padding: `${attributes?.loadMoreVerticalPadding}px ${attributes?.loadMoreHorizantalPadding}px`,
						borderRadius: `${attributes?.loadMoreBorderRadius}px`,
						borderWidth:`${attributes?.loadMoreBorderWidth}px`,
						borderColor: isHovered
								? attributes?.loadMoreHoverBorderColor
								: attributes?.loadMoreBorderColor,
						borderStyle: "solid",
						cursor: isLoading ? "not-allowed" : "pointer",
						opacity: isLoading ? 0.5 : 1,
						fontSize: calculateBtnFontSize(),
					}}
				>
					{attributes?.loadMoreButtonText ||  __("Load More", "astra-portfolio")}
				</button>
			)}
		</div>
	);
};

export default PortfolioList;
