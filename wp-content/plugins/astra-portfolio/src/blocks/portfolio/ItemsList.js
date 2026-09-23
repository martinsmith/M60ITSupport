import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import Masonry, { ResponsiveMasonry } from "react-responsive-masonry"; // Import masonry components
import he from "he";


const ItemsList = ({ attributes }) => {
	const [list, setList] = useState([]);
	const [isHovered, setIsHovered] = useState(false);
	const visibleItems = attributes.itemsPerPage;

	let filteredList;

	useEffect(() => {
		const fetchData = async () => {
			try {
				const baseUrl = astraPortfolioData.apiUrl;

				// Join category IDs with a comma if there are multiple selections
				const categoryParam =
					attributes?.categorySelectedNum &&
					attributes?.categorySelectedNum?.length > 0
						? `&astra-portfolio-categories=${attributes?.categorySelectedNum?.join(
								",",
						  )}`
						: "";

				// Construct the final URL
				const url = `${baseUrl}?per_page=${100}${categoryParam}`;

				const response = await fetch(url);
				let data = await response.json();
				// Filter by other categories
				if (attributes?.otherCategorySelectedLabels?.length > 0) {
					data = data?.filter(
						(item) =>
							item["astra-portfolio-other-categories"] &&
							item["astra-portfolio-other-categories"].some((id) =>
								attributes?.otherCategorySelectedNum?.includes(id),
							),
					);
				}
				// Filter by tags if selected
				if (attributes?.tagsSelectedLabels?.length > 0) {
					data = data.filter(
						(item) =>
							item["astra-portfolio-tags"] &&
							item["astra-portfolio-tags"].some((id) =>
								attributes?.tagsSelectedNum?.includes(id),
							),
					);
				}
				setList(data);
			} catch (error) {
				console.error("Error fetching data:", error);
			}
		};

		// Trigger data fetch on category selection change
		fetchData();
	}, [
		attributes?.categorySelectedNum,
		attributes?.otherCategorySelectedNum,
		attributes?.tagsSelectedNum,
	]);

	const calculateBtnFontSize = () => {
		return `${attributes?.loadMoreSize}px`;
	};

	return (
		<div
			className="astra-portfolio-shortcode-wrap astra-portfolio-grid astra-portfolio astra-portfolio-row"
			style={{ position: "relative" }}
		>
			{attributes.thumbnailHoverStyle === "style-1" ? (
				list?.slice(0, visibleItems).map((item) => (
					<div
						key={item?.id}
						className={`site-single new-tab iframe masonry-brick ${
							columnClass[attributes?.columns]
						}`}
					>
						<div className="inner">
							{attributes?.titlePosition === "top" && (
								<div className="template-meta">
									<div className="item-title">
									{he.decode(item?.title?.rendered)}
										<span className={`site-type ${item?.type}`}>
										{he.decode(item?.type)}
										</span>
									</div>
								</div>
							)}

							<span
								className="site-preview"
								data-title={item?.title?.rendered}
								style={{
									backgroundImage: `url(${item["thumbnail-image-url"]})`,
								}}
							>
								<span className="view-demo-wrap">
							<span className="view-demo">
								{attributes?.quickViewText || "Quick View"}
							</span>
						</span>
							</span>

							{attributes?.titlePosition === "bottom" && (
								<div className="template-meta">
									<div className="item-title">
									{he.decode(item?.title?.rendered)}
										<span className={`site-type ${item?.type}`}>
											{item?.type}
										</span>
									</div>
								</div>
							)}
						</div>
					</div>
				))
			) : (
				<div className="portfolio-grid">
					<ResponsiveMasonry
						columnsCountBreakPoints={{
							350: 1,
							500: 2,
							900: Number(attributes.columns),
						}}
						gutterBreakPoints={{ 0: "1px" }}
					>
						<Masonry gutter="1px">
							{list?.slice(0, visibleItems).map((item) => (
								<div key={item?.id}>
									<div
										className={`site-single new-tab iframe masonry-brick`}
									>
										<div className="inner">
											{attributes?.titlePosition === "top" && (
												<div className="template-meta">
													<div className="item-title">
													{he.decode(item?.title?.rendered)}
														<span className={`site-type ${item?.type}`}>
															{item?.type}
														</span>
													</div>
												</div>
											)}

											<span
												className="site-preview"
												data-title={item?.title?.rendered}
												style={{
													paddingTop: "0",
												}}
											>
												<img
													className="lazy"
													src={item["thumbnail-image-url"]}
													alt={item["thumbnail-image-meta"]["alt"]}
												/><span className="view-demo-wrap">
												<span className="view-demo">
													{attributes?.quickViewText || "Quick View"}
												</span>
											</span>
											</span>

											{attributes?.titlePosition === "bottom" && (
												<div className="template-meta">
													<div className="item-title">
													{he.decode(item?.title?.rendered)}
														<span className={`site-type ${item?.type}`}>
															{item?.type}
														</span>
													</div>
												</div>
											)}
										</div>
									</div>
								</div>
							))}
						</Masonry>
					</ResponsiveMasonry>
				</div>
			)}

			<div
				style={{
					width: "100%",
					display: "flex",
					justifyContent: "center",
					alignItems: "center",
				}}
			>
				<button
					onMouseEnter={() => setIsHovered(true)}
					onMouseLeave={() => setIsHovered(false)}
					style={{
						backgroundColor: 
							isHovered
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
						cursor: "pointer",
						fontSize: calculateBtnFontSize(),
					}}
				>
					{attributes?.loadMoreButtonText || "Load More"}
				</button>
			</div>

		</div>
	);
};

export default ItemsList;
