package omandam;
public class Employee {
    private int year;
    private double salary;
    private String name;
    private int id;
    private int age;
    private String sex;
    public Employee(){
        this(null,0,0,null,0,0);
    }
    public Employee(String name,int id,int age,String sex,double salary,int year){
        
        this.year = year;
        this.salary = salary;
        this.name = name;
        this.id = id;
        this.age = age;
        this.sex = sex;
    }
   
    
    public String getName(){
    return this.name;
    } 
    public int getID(){
      return this.id;
    }
    public int getAge(){
        return this.age;
    }
    public String getSex(){
        return this.sex;
    }
    public double getSalary(){
        return this.salary;
    }
    public int getYear(){
        return this.year;
     }
    public void setData(String name,int id,int age,String sex,double salary,int year){
        this.name = name;
        this.id = id;
        this.age = age;
        this.sex =sex;
        this.salary = salary;
        this.year = year;
    }
    public double bonus(){
        double bon = this.salary * 0.1* this.year;
        return bon;
    }
}


   
    
    
        
    

    

