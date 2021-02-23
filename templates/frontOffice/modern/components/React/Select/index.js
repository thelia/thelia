import React, { forwardRef } from 'react';

import Error from '../Error';

const Select = forwardRef(
	({ label, name, options = [], error, className = '', ...props }, ref) => {
		return (
			<label className={`${className ? className : 'w-full block'}`}>
				{label ? (
					<div
						className={`font-bold text-sm ${
							error ? 'text-red-500' : 'text-gray-700'
						} `}
					>
						{label}
					</div>
				) : null}

				<select
					ref={ref}
					name={name}
					{...props}
					value={props.defaultValue || props.value}
					className="block w-full mt-0 px-0.5 border-0 border-b-2 border-gray-200 focus:ring-0 focus:border-black"
				>
					{options.map((option, index) => {
						return (
							<option
								key={index}
								className={option.className}
								value={option.value}
							>
								{option.label}
							</option>
						);
					})}
				</select>
				{error ? (
					<div className="mt-1">
						<Error error={error} />
					</div>
				) : null}
			</label>
		);
	}
);

export default Select;
