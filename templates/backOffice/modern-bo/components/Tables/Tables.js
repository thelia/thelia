import React from "react";
import { TableContent } from "./TableContent";

export const Tables = () => {
  
      const data = React.useMemo(
        () => [
          {
            col1: 'Hello',
            col2: 'World',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'react-table',
            col2: 'rocks',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
          {
            col1: 'whatever',
            col2: 'you want',
            col3: 'boom',
            col4: 'efziojzeijfpez',
          },
        ],
        []
      )
    
      const columns = React.useMemo(
        () => [
          {
            Header: 'ID',
            accessor: 'col1', 
          },
          {
            Header: 'NOM',
            accessor: 'col2', //
          },
          {
            Header: 'CONTENU LIÉS',
            accessor: 'col3', //
          },
          {
            Header: 'LANGUES DISPONIBLES',
            accessor: 'col4', //
    
          },
          {
            Header: 'ACTIONS',
            Cell: ({ cell: {value} }) => (
              <div>
              <button>
                bim{value}
              </button>
              <button>
                bam{value}
              </button>
              <button>
                boom{value}
              </button>
              </div>
            )
          },
        ],
        []
      )
    
      return (
        <div className="App">
          <TableContent columns={columns} data={data} />
        </div>
      );
    }
    
    export default Tables;