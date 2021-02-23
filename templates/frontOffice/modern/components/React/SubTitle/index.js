import React from 'react';

export default function SubTitle({ subTitle, className }) {
	return (
		<div
			className={`text-3xl font-bold text-left outline-none mb-8 leading-none ${className}`}
		>
			{subTitle}
		</div>
	);
}
